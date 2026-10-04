<?php

namespace App\Enums;

enum CaseFileType: string
{
    case Report = 'report';
    case Image = 'image';
    case Video = 'video';
    case Referral = 'referral';
    case Dicom = 'dicom';

    /**
     * Column title as it appears in the doctors' table.
     */
    public function label(): string
    {
        return match ($this) {
            self::Report => 'التقارير / الملفات السابقة',
            self::Image => 'الصور', self::Video => 'الفيديوهات',
            self::Referral => 'Referral sheet',
            self::Dicom => 'DICOM',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Report => 'تقارير',
            self::Image => 'صور', self::Video => 'فيديو',
            self::Referral => 'Referral',
            self::Dicom => 'DICOM',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Report => 'تقارير PDF وصور التقارير',
            self::Image => 'صور الأشعة والفحص', self::Video => 'فيديو الفحص أو الشرح',
            self::Referral => 'صورة طلب الطبيب',
            self::Dicom => 'ملف DICOM مضغوط أو مباشر',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Report => 'document-text',
            self::Image => 'photo', self::Video => 'video-camera',
            self::Referral => 'clipboard-document-list',
            self::Dicom => 'cube',
        };
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Report => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            self::Image => ['jpg', 'jpeg', 'png', 'webp'], self::Video => ['mp4', 'webm', 'mov'],
            self::Referral => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            self::Dicom => ['zip', 'rar', '7z', 'dcm', 'iso'],
        };
    }

    /**
     * Allowed detected MIME types; null means the type is not sniffed
     * (archives and raw DICOM report inconsistent MIME types).
     *
     * @return list<string>|null
     */
    public function mimeTypes(): ?array
    {
        return match ($this) {
            self::Report, self::Referral => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
            self::Image => ['image/jpeg', 'image/png', 'image/webp'], self::Video => ['video/mp4', 'video/webm', 'video/quicktime'],
            self::Dicom => null,
        };
    }

    public function maxBytes(): int
    {
        return (int) config("radiology.max_upload_mb.{$this->value}", 50) * 1024 * 1024;
    }

    public function accept(): string
    {
        return collect($this->extensions())->map(fn (string $ext) => '.'.$ext)->implode(',');
    }
}
