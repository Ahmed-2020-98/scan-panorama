# إعداد Google Drive

## الربط

1. فعّل Google Drive API في مشروع Google Cloud الخاص بالمركز، واضبط شاشة OAuth (أضف حساب المركز ضمن مستخدمي الاختبار عند استخدام وضع Testing).
2. أنشئ OAuth client من نوع Web application. أضف عنوان الرجوع المطابق تمامًا: `http://localhost:8011/drive/callback` محليًا، أو `https://YOUR-DOMAIN/drive/callback` للإنتاج.
3. املأ `GOOGLE_DRIVE_CLIENT_ID` و`GOOGLE_DRIVE_CLIENT_SECRET` و`GOOGLE_DRIVE_REDIRECT_URI` في `.env` على الخادم. لا تُرسل الأسرار في المحادثة ولا تُضمّنها في Git.
4. نفّذ `php artisan config:clear`، ثم افتح «Google Drive والملفات» بحساب Manager واربط حساب المركز. النظام يطلب نطاق `drive.file` للملفات التي ينشئها التطبيق، ويحفظ refresh token مشفرًا بمفتاح التطبيق. احتفظ بنسخة آمنة من `APP_KEY` مع النسخ الاحتياطية.
5. My Drive هو الوجهة الافتراضية. يمكن تحديد Shared Drive ID قبل أول رفع فقط إذا كان الحساب يملك صلاحية الإنشاء فيه. ملفات Shared Drive ترث صلاحيات أعضائه؛ النظام لا ينشئ مشاركة عامة. إعادة الربط تقبل الحساب نفسه وتحافظ على المجلدات؛ تغيير الحساب بعد الرفع يحتاج ترحيلًا مستقلًا.

ينشئ النظام: `Radiology Center / Branch / Year / Patient + File Number / Case Code / Images | Reports | DICOM | Videos | Requests`.

## التشغيل المستمر

```bash
php artisan queue:work --queue=default --sleep=3 --tries=5 --timeout=1800
php artisan schedule:run
```

شغّل العامل تحت Supervisor/systemd وأعد تشغيله بعد كل إصدار باستخدام `php artisan queue:restart`. أضف `schedule:run` إلى cron كل دقيقة. يجب أن يكون `DB_QUEUE_RETRY_AFTER=1900` (أكبر من timeout العامل)، و`QUEUE_CONNECTION=database`. للإنتاج اجعل `APP_DEBUG=false` وعنوان التطبيق HTTPS.

رفع المتصفح يصل على أجزاء إلى staging خاص على الخادم، ثم ينقله العامل إلى Drive. يصبح الملف قابلًا للفتح والمشاركة بعد تأكيد الحجم وMD5، ويُحذف staging عند النجاح. إعادة المحاولة تستخدم provider ID نفسه. تظهر حالة فشل واضحة بعد استنفاد المحاولات، ويمكن إعادة المحاولة من الحالة خلال سبعة أيام. الأمر المجدول ينظف staging القديم وجلسات الأجزاء المهجورة. يجب توفير مساحة مؤقتة تكفي الرفعات المتزامنة؛ Drive يقلل التخزين الدائم ولا يلغي التخزين المؤقت أثناء النقل.

الصور وPDF والفيديو تُفتح عبر النظام؛ ZIP وDICOM تُحمّل. تشغيل الفيديو يعتمد على صيغة/ترميز المتصفح، ويمكن تنزيل الملف عند عدم دعم المعاينة. الملفات القديمة تستخدم قرصها الأصلي ولا تُرحَّل تلقائيًا. إضافة الملفات يدويًا إلى Drive لا تُزامَن في الـMVP.

## قبول الاتصال الحقيقي — لم ينفذ بعد

بعد الربط، استخدم حالة تجريبية جديدة وملفات صناعية فقط:

- صورة وPDF وفيديو صغير: تحقق من المجلدات وحالة «جاهز» وفتح/تنزيل المحتوى.
- ارفع ملفين بالاسم نفسه وتحقق من احتفاظهما بمحتوى منفصل.
- أوقف العامل مؤقتًا ثم أعده، وراجع pending ثم ready دون تكرار المرفقات.
- استبعد ملفًا من الرابط، وألغ الرابط، وتحقق من منع فتحه.
- راجع صلاحيات المجلد وأعضاء Shared Drive إن استُخدم.

اختبارات HTTP المحاكية اجتازت النقل والتحقق، لكنها لا تثبت نجاح حساب Google الحقيقي. قد يتطلب وضع OAuth Testing إعادة التفويض عند انتهاء صلاحية refresh token؛ اضبط نشر تطبيق OAuth قبل الاعتماد اليومي.

## المصادر الرسمية

- [Google OAuth على خادم الويب](https://developers.google.com/identity/protocols/oauth2/web-server)
- [نطاقات Drive](https://developers.google.com/workspace/drive/api/guides/api-specific-auth)
- [رفع الملفات والنقل القابل للاستئناف](https://developers.google.com/workspace/drive/api/guides/manage-uploads)
- [إنشاء الملفات ومعرّفات تمنع تكرار الإنشاء](https://developers.google.com/workspace/drive/api/guides/create-file)
- [إنشاء الملفات ودعم Shared Drive](https://developers.google.com/workspace/drive/api/reference/rest/v3/files/create)
