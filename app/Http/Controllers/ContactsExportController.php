<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\CampaignAudience;
use App\Support\ActivityLogger;
use App\Support\Phone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * vCard export so staff can add patients/doctors to the center's phone: WhatsApp
 * only shows the center's status to people saved in its contacts.
 */
class ContactsExportController extends Controller
{
    public function __invoke(Request $request, string $audience): StreamedResponse
    {
        $branch = $request->integer('branch') ?: null;
        $rows = CampaignAudience::for($request->user(), $audience, $branch);
        ActivityLogger::log('contacts.exported', null, ['audience' => $audience, 'count' => $rows->count()]);
        $tag = $audience === 'doctors' ? 'طبيب' : (string) (Setting::get('center_name') ?: config('radiology.center_name'));
        $escape = fn (string $v) => str_replace([';', ',', "\n"], ['\;', '\,', ' '], $v);

        return response()->streamDownload(function () use ($rows, $escape, $tag, $audience) {
            foreach ($rows as $row) {
                $name = $audience === 'doctors' ? $row['name'] : $row['name'].' - '.$tag;
                echo "BEGIN:VCARD\r\nVERSION:3.0\r\nFN:".$escape($name)."\r\nN:".$escape($name).";;;;\r\nTEL;TYPE=CELL:+".Phone::toInternational($row['phone'])."\r\nEND:VCARD\r\n";
            }
        }, ($audience === 'doctors' ? 'doctors' : 'patients').'-'.today()->format('Y-m-d').'.vcf', ['Content-Type' => 'text/vcard; charset=UTF-8']);
    }
}
