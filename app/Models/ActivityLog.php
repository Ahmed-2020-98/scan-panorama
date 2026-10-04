<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property int|null $medical_case_id
 * @property array<string, mixed>|null $meta
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read MedicalCase|null $medicalCase
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id', 'medical_case_id', 'meta', 'ip', 'user_agent'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'login' => 'تسجيل دخول',
        'patient.created' => 'إضافة مريض', 'patient.updated' => 'تعديل مريض', 'patient.deleted' => 'حذف مريض', 'patient.recovered' => 'استرجاع مريض',
        'case.recovered' => 'استرجاع حالة', 'file.recovered' => 'استرجاع ملف', 'case.clinical_updated' => 'تحديث بيانات الفحص',
        'price.updated' => 'تعديل السعر', 'price.discounted' => 'تطبيق خصم', 'payment.received' => 'تسجيل تحصيل', 'payment.refunded' => 'رد مبلغ',
        'user.created' => 'إنشاء مستخدم', 'user.updated' => 'تعديل مستخدم وصلاحياته',
        'visit.saved' => 'حفظ زيارة طبيب', 'visit.deleted' => 'حذف زيارة طبيب', 'share.files_updated' => 'تحديث ملفات الرابط',
        'exam.updated' => 'تعديل الفحص والأسعار', 'exam.deleted' => 'حذف نوع فحص', 'branch.created' => 'إنشاء فرع', 'branch.updated' => 'تعديل فرع', 'branch.deleted' => 'حذف فرع',
        'doctor.created' => 'إضافة طبيب', 'doctor.updated' => 'تعديل طبيب', 'doctor.deleted' => 'حذف طبيب',
        'drive.connected' => 'ربط Drive', 'drive.destination_updated' => 'تعديل وجهة Drive', 'file.drive_ready' => 'اكتمال نقل الملف إلى Drive', 'settings.updated' => 'تعديل إعدادات المركز',
        'case.created' => 'إنشاء حالة',
        'case.updated' => 'تعديل حالة',
        'case.archived' => 'أرشفة حالة',
        'case.restored' => 'إلغاء أرشفة حالة',
        'case.deleted' => 'حذف حالة',
        'file.uploaded' => 'رفع ملف',
        'file.deleted' => 'حذف ملف',
        'file.viewed' => 'فتح ملف',
        'file.downloaded' => 'تحميل ملف',
        'share.copied' => 'نسخ رابط المشاركة',
        'share.whatsapp' => 'إرسال عبر واتساب',
        'share.regenerated' => 'إصدار رابط جديد',
        'share.revoked' => 'إلغاء رابط المشاركة',
        'share.opened' => 'فتح رابط المشاركة',
        'portal.opened' => 'فتح الحالة من حساب الطبيب',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MedicalCase, $this>
     */
    public function medicalCase(): BelongsTo
    {
        return $this->belongsTo(MedicalCase::class)->withTrashed();
    }

    public function label(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function actorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return str_starts_with($this->action, 'share.') || str_starts_with($this->action, 'file.')
            ? 'زائر عبر رابط المشاركة'
            : 'النظام';
    }
}
