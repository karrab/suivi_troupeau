<?php
defined('INC') || exit; // fichier inclus uniquement
/**
 * Moteur de formules sécurisé (sans eval) pour les indices zootechniques.
 * Utilisation : (new MoteurFormule())->evaluer('a / b * 100', ['a' => 3, 'b' => 4]);
 *
 * Grammaire :
 *   expr     := or
 *   or       := and ( ('||' | 'ou') and )*
 *   and      := cmp ( ('&&' | 'et') cmp )*
 *   cmp      := add ( ('<'|'<='|'>'|'>='|'=='|'!=') add )?
 *   add      := mul ( ('+'|'-') mul )*
 *   mul      := unary ( ('*'|'/'|'%') unary )*
 *   unary    := ('-'|'+'|'!') unary | pow
 *   pow      := primary ( '^' unary )?
 *   primary  := nombre | variable | fonction '(' args ')' | '(' expr ')'
 *
 * Fonctions : min, max, abs, round(x[,n]), floor, ceil, sqrt, si(cond, a, b),
 *             coalesce(a, b, ...), nz(x) (null => 0), pct(a, b) = a / b * 100.
 * Une division par zéro ou une variable nulle donne null (=> "non calculable").
 */
final class MoteurFormule
{
    private const FUNCS = [
        'min' => [1, 20], 'max' => [1, 20], 'abs' => [1, 1], 'round' => [1, 2], 'floor' => [1, 1],
        'ceil' => [1, 1], 'sqrt' => [1, 1], 'si' => [3, 3], 'if' => [3, 3], 'coalesce' => [1, 20],
        'nz' => [1, 1], 'pct' => [2, 2],
    ];

    private array $tokens = [];
    private int $pos = 0;
    private static array $astCache = [];

    /** Analyse une formule ; lève InvalidArgumentException si invalide. */
    public function analyser(string $formula): array
    {
        if (isset(self::$astCache[$formula])) {
            return self::$astCache[$formula];
        }
        $this->tokens = $this->tokenize($formula);
        $this->pos = 0;
        if (!$this->tokens) {
            throw new InvalidArgumentException('Formule vide.');
        }
        $ast = $this->parseOr();
        if ($this->pos < count($this->tokens)) {
            throw new InvalidArgumentException('Élément inattendu : « ' . $this->tokens[$this->pos][1] . ' ».');
        }
        return self::$astCache[$formula] = $ast;
    }

    /** Liste des variables utilisées par la formule. */
    public function variables(string $formula): array
    {
        $vars = [];
        $walk = static function (array $n) use (&$walk, &$vars): void {
            if ($n[0] === 'var') {
                $vars[$n[1]] = true;
            }
            foreach (array_slice($n, 1) as $child) {
                if (is_array($child)) {
                    if (isset($child[0]) && is_string($child[0])) {
                        $walk($child);
                    } else {
                        foreach ($child as $c) {
                            if (is_array($c)) {
                                $walk($c);
                            }
                        }
                    }
                }
            }
        };
        $walk($this->analyser($formula));
        return array_keys($vars);
    }

    /**
     * Valide la formule et vérifie que les variables existent.
     * @return string|null message d'erreur ou null si valide
     */
    public function valider(string $formula, array $allowedVars): ?string
    {
        try {
            $unknown = array_diff($this->variables($formula), $allowedVars);
            if ($unknown) {
                return 'Variable(s) inconnue(s) : ' . implode(', ', $unknown);
            }
        } catch (InvalidArgumentException $e) {
            return $e->getMessage();
        }
        return null;
    }

    public function evaluer(string $formula, array $vars): ?float
    {
        $v = $this->evalNode($this->analyser($formula), $vars);
        if ($v === null || is_nan($v) || is_infinite($v)) {
            return null;
        }
        return (float) $v;
    }

    // ------------------------------------------------------------------ lexer
    private function tokenize(string $s): array
    {
        $tokens = [];
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($s[$i + 1]))) {
                $j = $i;
                while ($j < $len && (ctype_digit($s[$j]) || $s[$j] === '.')) {
                    $j++;
                }
                $num = substr($s, $i, $j - $i);
                if (substr_count($num, '.') > 1) {
                    throw new InvalidArgumentException("Nombre invalide : $num");
                }
                $tokens[] = ['num', (float) $num];
                $i = $j;
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $j = $i;
                while ($j < $len && (ctype_alnum($s[$j]) || $s[$j] === '_')) {
                    $j++;
                }
                $word = strtolower(substr($s, $i, $j - $i));
                $tokens[] = match ($word) {
                    'et', 'and' => ['op', '&&'],
                    'ou', 'or'  => ['op', '||'],
                    default     => ['id', $word],
                };
                $i = $j;
                continue;
            }
            $two = substr($s, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['op', $two];
                $i += 2;
                continue;
            }
            if (str_contains('+-*/%^<>!(),', $c)) {
                $tokens[] = [$c === '(' || $c === ')' || $c === ',' ? $c : 'op', $c];
                $i++;
                continue;
            }
            if ($c === '=') {
                $tokens[] = ['op', '=='];
                $i++;
                continue;
            }
            throw new InvalidArgumentException("Caractère non autorisé : « $c ».");
        }
        return $tokens;
    }

    // ----------------------------------------------------------------- parser
    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function isOp(string ...$ops): bool
    {
        $t = $this->peek();
        return $t && $t[0] === 'op' && in_array($t[1], $ops, true);
    }

    private function expect(string $type): void
    {
        $t = $this->peek();
        if (!$t || $t[0] !== $type) {
            throw new InvalidArgumentException("« $type » attendu" . ($t ? ' près de « ' . $t[1] . ' »' : ' en fin de formule') . '.');
        }
        $this->pos++;
    }

    private function parseOr(): array
    {
        $n = $this->parseAnd();
        while ($this->isOp('||')) {
            $this->pos++;
            $n = ['bin', '||', $n, $this->parseAnd()];
        }
        return $n;
    }

    private function parseAnd(): array
    {
        $n = $this->parseCmp();
        while ($this->isOp('&&')) {
            $this->pos++;
            $n = ['bin', '&&', $n, $this->parseCmp()];
        }
        return $n;
    }

    private function parseCmp(): array
    {
        $n = $this->parseAdd();
        if ($this->isOp('<', '<=', '>', '>=', '==', '!=')) {
            $op = $this->tokens[$this->pos++][1];
            $n = ['bin', $op, $n, $this->parseAdd()];
        }
        return $n;
    }

    private function parseAdd(): array
    {
        $n = $this->parseMul();
        while ($this->isOp('+', '-')) {
            $op = $this->tokens[$this->pos++][1];
            $n = ['bin', $op, $n, $this->parseMul()];
        }
        return $n;
    }

    private function parseMul(): array
    {
        $n = $this->parseUnary();
        while ($this->isOp('*', '/', '%')) {
            $op = $this->tokens[$this->pos++][1];
            $n = ['bin', $op, $n, $this->parseUnary()];
        }
        return $n;
    }

    private function parseUnary(): array
    {
        if ($this->isOp('-', '+', '!')) {
            $op = $this->tokens[$this->pos++][1];
            return ['un', $op, $this->parseUnary()];
        }
        return $this->parsePow();
    }

    private function parsePow(): array
    {
        $n = $this->parsePrimary();
        if ($this->isOp('^')) {
            $this->pos++;
            $n = ['bin', '^', $n, $this->parseUnary()];
        }
        return $n;
    }

    private function parsePrimary(): array
    {
        $t = $this->peek();
        if (!$t) {
            throw new InvalidArgumentException('Formule incomplète.');
        }
        if ($t[0] === 'num') {
            $this->pos++;
            return ['num', $t[1]];
        }
        if ($t[0] === '(') {
            $this->pos++;
            $n = $this->parseOr();
            $this->expect(')');
            return $n;
        }
        if ($t[0] === 'id') {
            $this->pos++;
            $next = $this->peek();
            if ($next && $next[0] === '(') {
                $name = $t[1];
                if (!isset(self::FUNCS[$name])) {
                    throw new InvalidArgumentException("Fonction inconnue : $name()");
                }
                $this->pos++;
                $args = [];
                if (!($this->peek() && $this->peek()[0] === ')')) {
                    $args[] = $this->parseOr();
                    while ($this->peek() && $this->peek()[0] === ',') {
                        $this->pos++;
                        $args[] = $this->parseOr();
                    }
                }
                $this->expect(')');
                [$minA, $maxA] = self::FUNCS[$name];
                if (count($args) < $minA || count($args) > $maxA) {
                    throw new InvalidArgumentException("Nombre d'arguments invalide pour $name().");
                }
                return ['fn', $name, $args];
            }
            return ['var', $t[1]];
        }
        throw new InvalidArgumentException('Élément inattendu : « ' . $t[1] . ' ».');
    }

    // -------------------------------------------------------------- évaluation
    private function evalNode(array $n, array $vars): ?float
    {
        switch ($n[0]) {
            case 'num':
                return $n[1];
            case 'var':
                if (!array_key_exists($n[1], $vars)) {
                    throw new InvalidArgumentException('Variable inconnue : ' . $n[1]);
                }
                return $vars[$n[1]] === null ? null : (float) $vars[$n[1]];
            case 'un':
                $v = $this->evalNode($n[2], $vars);
                if ($n[1] === '!') {
                    return $v ? 0.0 : 1.0;
                }
                return $v === null ? null : ($n[1] === '-' ? -$v : $v);
            case 'bin':
                [$_, $op, $l, $r] = $n;
                if ($op === '&&') {
                    return ($this->evalNode($l, $vars) && $this->evalNode($r, $vars)) ? 1.0 : 0.0;
                }
                if ($op === '||') {
                    return ($this->evalNode($l, $vars) || $this->evalNode($r, $vars)) ? 1.0 : 0.0;
                }
                $a = $this->evalNode($l, $vars);
                $b = $this->evalNode($r, $vars);
                if ($a === null || $b === null) {
                    return null;
                }
                return match ($op) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? null : $a / $b,
                    '%' => $b == 0.0 ? null : fmod($a, $b),
                    '^' => $a ** $b,
                    '<' => (float) ($a < $b),
                    '<=' => (float) ($a <= $b),
                    '>' => (float) ($a > $b),
                    '>=' => (float) ($a >= $b),
                    '==' => (float) (abs($a - $b) < 1e-9),
                    '!=' => (float) (abs($a - $b) >= 1e-9),
                };
            case 'fn':
                return $this->callFn($n[1], $n[2], $vars);
        }
        return null;
    }

    private function callFn(string $name, array $args, array $vars): ?float
    {
        if ($name === 'si' || $name === 'if') {
            $cond = $this->evalNode($args[0], $vars);
            return $this->evalNode($cond ? $args[1] : $args[2], $vars);
        }
        if ($name === 'coalesce') {
            foreach ($args as $a) {
                $v = $this->evalNode($a, $vars);
                if ($v !== null) {
                    return $v;
                }
            }
            return null;
        }
        if ($name === 'nz') {
            return $this->evalNode($args[0], $vars) ?? 0.0;
        }
        $vals = array_map(fn($a) => $this->evalNode($a, $vars), $args);
        if (in_array(null, $vals, true)) {
            return null;
        }
        return match ($name) {
            'min'   => min($vals),
            'max'   => max($vals),
            'abs'   => abs($vals[0]),
            'round' => round($vals[0], (int) ($vals[1] ?? 0)),
            'floor' => floor($vals[0]),
            'ceil'  => ceil($vals[0]),
            'sqrt'  => $vals[0] < 0 ? null : sqrt($vals[0]),
            'pct'   => $vals[1] == 0.0 ? null : $vals[0] / $vals[1] * 100,
            default => null,
        };
    }
}
