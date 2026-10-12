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
    case ManageDoctors = 'manage_doctors';
    case ViewAllFinancials = 'view_all_financials';
    case ManageExpenses = 'manage_expenses';
    case SendCampaigns = 'send_campaigns';
    case ViewActivity = 'view_activity';
    case ManageSettings = 'manage_settings';

    /**
     * Permissions an admin can grant to a user (discounts were retired).
     *
     * @return list<self>
     */
    public static function grantable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p) => $p !== self::ApplyDiscount));
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewCases => 'عرض الحالات', self::CreateCase => 'إنشاء حالة',
            self::EditCase => 'تعديل الحالة', self::DeleteCase => 'الحذف والمحذوفات',
            self::UploadFiles => 'رفع الملفات', self::ShareCase => 'مشاركة الحالة',
            self::ViewFinancials => 'الحسابات (اليوم)',
            self::ApplyDiscount => 'تطبيق الخصومات', self::ManageUsers => 'إدارة المستخدمين',
            self::ManageVisits => 'إدارة الزيارات', self::ManageBranches => 'إدارة الفروع',
            self::ManageExamTypes => 'إدارة أنواع الفحوصات والأسعار',
            self::ManageDoctors => 'إدارة الأطباء وروابطهم',
            self::ViewAllFinancials => 'الحسابات لكل الفترات والفروع ورد المبالغ',
            self::ManageExpenses => 'المصروفات والرواتب',
            self::SendCampaigns => 'رسائل العروض للمرضى والأطباء',
            self::ViewActivity => 'سجل النشاط',
            self::ManageSettings => 'إعدادات المركز',
        };
    }
}
