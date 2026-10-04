<?php

namespace App\Enums;

enum Permission: string
{
    case ViewCases = 'view_cases';
    case CreateCase = 'create_case';
    case EditCase = 'edit_case';
    case DeleteCase = 'delete_case';
    case UploadFiles = 'upload_files';
    case ShareCase = 'share_case';
    case ViewFinancials = 'view_financials';
    case ApplyDiscount = 'apply_discount';
    case ManageUsers = 'manage_users';
    case ManageVisits = 'manage_visits';
    case ManageBranches = 'manage_branches';
    case ManageExamTypes = 'manage_exam_types';

    public function label(): string
    {
        return match ($this) {
            self::ViewCases => 'عرض الحالات', self::CreateCase => 'إنشاء حالة',
            self::EditCase => 'تعديل الحالة', self::DeleteCase => 'حذف الحالة والملفات',
            self::UploadFiles => 'رفع الملفات', self::ShareCase => 'مشاركة الحالة',
            self::ViewFinancials => 'حسابات اليوم للاستقبال / جميع الحسابات للمدير',
            self::ApplyDiscount => 'تطبيق الخصومات', self::ManageUsers => 'إدارة المستخدمين',
            self::ManageVisits => 'إدارة الزيارات', self::ManageBranches => 'إدارة الفروع',
            self::ManageExamTypes => 'إدارة أنواع الفحوصات',
        };
    }
}
