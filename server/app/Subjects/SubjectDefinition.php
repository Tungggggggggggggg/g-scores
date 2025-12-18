<?php

namespace App\Subjects;

final class SubjectDefinition implements Subject
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $column,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function column(): string
    {
        return $this->column;
    }
}
