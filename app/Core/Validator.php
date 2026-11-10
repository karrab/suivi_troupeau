<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validation simple : règles "required|date|numeric|min:0|max:5|in:a,b|int|maxlen:30|email".
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(private array $input, private array $rules, private array $labels = [])
    {
    }

    public static function make(array $input, array $rules, array $labels = []): self
    {
        $v = new self($input, $rules, $labels);
        $v->run();
        return $v;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $rules = is_array($ruleStr) ? $ruleStr : explode('|', $ruleStr);
            $label = $this->labels[$field] ?? $field;

            if ($value === '' || $value === null) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = "Le champ « $label » est obligatoire.";
                }
                $this->clean[$field] = in_array('bool', $rules, true) ? 0 : null;
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $err = match ($name) {
                    'date'    => self::isDate((string) $value) ? null : "« $label » doit être une date valide.",
                    'numeric' => is_numeric(str_replace(',', '.', (string) $value)) ? null : "« $label » doit être un nombre.",
                    'int'     => filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "« $label » doit être un entier.",
                    'min'     => (float) str_replace(',', '.', (string) $value) >= (float) $arg ? null : "« $label » doit être ≥ $arg.",
                    'max'     => (float) str_replace(',', '.', (string) $value) <= (float) $arg ? null : "« $label » doit être ≤ $arg.",
                    'maxlen'  => mb_strlen((string) $value) <= (int) $arg ? null : "« $label » : $arg caractères maximum.",
                    'minlen'  => mb_strlen((string) $value) >= (int) $arg ? null : "« $label » : $arg caractères minimum.",
                    'in'      => in_array((string) $value, explode(',', (string) $arg), true) ? null : "Valeur invalide pour « $label ».",
                    'email'   => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : "« $label » doit être un e-mail valide.",
                    'regex'   => preg_match((string) $arg, (string) $value) ? null : "Format invalide pour « $label ».",
                    default   => null,
                };
                if ($err) {
                    $this->errors[$field] = $err;
                    break;
                }
            }
            if (in_array('numeric', $rules, true)) {
                $value = str_replace(',', '.', (string) $value);
            }
            if (in_array('bool', $rules, true)) {
                $value = $value ? 1 : 0;
            }
            $this->clean[$field] = $value;
        }
    }

    public static function isDate(string $v): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }

    public function fails(): bool
    {
        return (bool) $this->errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function data(): array
    {
        return $this->clean;
    }

    public function addError(string $field, string $msg): void
    {
        $this->errors[$field] = $msg;
    }
}
