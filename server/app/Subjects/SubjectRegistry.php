<?php

namespace App\Subjects;

final class SubjectRegistry
{
    public static function all(): array
    {
        return [
            new SubjectDefinition('toan', 'Toán', 'toan'),
            new SubjectDefinition('ngu_van', 'Ngữ văn', 'ngu_van'),
            new SubjectDefinition('ngoai_ngu', 'Ngoại ngữ', 'ngoai_ngu'),
            new SubjectDefinition('vat_li', 'Vật lí', 'vat_li'),
            new SubjectDefinition('hoa_hoc', 'Hóa học', 'hoa_hoc'),
            new SubjectDefinition('sinh_hoc', 'Sinh học', 'sinh_hoc'),
            new SubjectDefinition('lich_su', 'Lịch sử', 'lich_su'),
            new SubjectDefinition('dia_li', 'Địa lí', 'dia_li'),
            new SubjectDefinition('gdcd', 'GDCD', 'gdcd'),
        ];
    }

    public static function groupA(): array
    {
        return [
            new SubjectDefinition('toan', 'Toán', 'toan'),
            new SubjectDefinition('vat_li', 'Vật lí', 'vat_li'),
            new SubjectDefinition('hoa_hoc', 'Hóa học', 'hoa_hoc'),
        ];
    }
}
