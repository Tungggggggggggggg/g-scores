<?php

namespace App\Subjects;

interface Subject
{
    public function key(): string;

    public function label(): string;

    public function column(): string;
}
