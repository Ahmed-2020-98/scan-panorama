<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('mail:test {to : Address to send the test email to}')]
#[Description('Send a short test email to check the mail settings (password reset links use the same settings)')]
class SendTestMail extends Command
{
    public function handle(): int
    {
        $to = (string) $this->argument('to');
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Not a valid email address.');

            return self::FAILURE;
        }

        try {
            Mail::raw('دي رسالة تجربة من '.Setting::get('center_name').'. لو وصلتك، يبقى «نسيت كلمة المرور» شغال.', function ($message) use ($to) {
                $message->to($to)->subject('تجربة الإيميل');
            });
        } catch (Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent with mailer ['.config('mail.default').'] from ['.config('mail.from.address').'] to ['.$to.'].');

        return self::SUCCESS;
    }
}
