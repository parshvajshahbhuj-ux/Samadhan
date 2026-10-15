<?php

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . "/vendor/autoload.php";

function generateTicketReceiptPdf(array $ticket, string $completedAt): string
{
    foreach (["id", "name", "email", "subject", "category", "priority", "status", "description", "created_at"] as $field) {
        if (!array_key_exists($field, $ticket)) {
            throw new InvalidArgumentException("Ticket receipt is missing required data.");
        }
    }

    $escape = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    };
    $formatDate = static function (string $value): string {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            throw new RuntimeException("Ticket receipt contains an invalid date.");
        }
        return date("M j, Y g:i A", $timestamp);
    };
    $stampContents = file_get_contents(__DIR__ . "/assets/images/samadhan-authorized-stamp.svg");
    $completedStampContents = file_get_contents(__DIR__ . "/assets/images/ticket-completed-stamp-print.svg");
    $signatureContents = file_get_contents(__DIR__ . "/assets/images/parshva-shah-signature.svg");
    if ($stampContents === false || $completedStampContents === false || $signatureContents === false) {
        throw new RuntimeException("Unable to load receipt authorization artwork.");
    }
    $stampImage = "data:image/svg+xml;base64," . base64_encode($stampContents);
    $completedStampImage = "data:image/svg+xml;base64," . base64_encode($completedStampContents);
    $signatureImage = "data:image/svg+xml;base64," . base64_encode($signatureContents);
    $receiptReference = "SDN-TKT-" . str_pad((string)(int)$ticket["id"], 6, "0", STR_PAD_LEFT);

    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><style>
        @page { margin: 42px; }
        body { color: #18364a; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.55; }
        .header { border-bottom: 2px solid #087fb1; padding-bottom: 16px; }
        .brand { color: #082f49; font-size: 23px; font-weight: bold; }
        .tagline { color: #647b8d; font-size: 10px; }
        .kind { color: #087da5; font-size: 9px; font-weight: bold; letter-spacing: 2px; margin-top: 20px; }
        h1 { color: #082f49; font-size: 25px; margin: 5px 0; }
        .subject { color: #526b7d; font-size: 14px; }
        .status { background: #dcfce7; border-radius: 12px; color: #15803d; display: inline-block; font-weight: bold; margin-top: 10px; padding: 5px 10px; }
        table { border-collapse: collapse; margin-top: 22px; width: 100%; }
        td { border-bottom: 1px solid #e5edf1; padding: 10px 4px; vertical-align: top; width: 50%; }
        .label { color: #7a8d9b; display: block; font-size: 8px; font-weight: bold; letter-spacing: 1px; margin-bottom: 4px; text-transform: uppercase; }
        .description { background: #f8fafc; border: 1px solid #e2eaf0; margin-top: 22px; padding: 15px; }
        .description-title { color: #18364a; font-size: 12px; font-weight: bold; margin: 0 0 8px; }
        .authorization { border: 1px solid #dce7ee; background: #f8fbfc; margin-top: 20px; padding: 14px; }
        .authorization td { border: 0; padding: 4px 7px; vertical-align: middle; }
        .stamp { height: 92px; width: 92px; }
        .completed-stamp { height: 108px; width: 108px; }
        .signature { height: 58px; width: 168px; }
        .signoff { color: #18364a; font-size: 11px; font-weight: bold; text-align: center; }
        .signoff-title { color: #7a8d9b; font-size: 9px; font-weight: normal; }
        .footer { border-top: 1px solid #e5edf1; color: #8798a5; font-size: 9px; margin-top: 28px; padding-top: 12px; }
    </style></head><body>
        <div class="header"><div class="brand">Samadhan</div><div class="tagline">Support &amp; Resolution Desk</div></div>
        <div class="kind">RESOLUTION RECEIPT</div>
        <h1>Ticket #' . (int)$ticket["id"] . '</h1>
        <div class="subject">' . $escape($ticket["subject"]) . '</div>
        <div class="status">' . $escape($ticket["status"]) . '</div>
        <table>
            <tr><td><span class="label">Customer</span>' . $escape($ticket["name"]) . '</td><td><span class="label">Email</span>' . $escape($ticket["email"]) . '</td></tr>
            <tr><td><span class="label">Category</span>' . $escape($ticket["category"]) . '</td><td><span class="label">Priority</span>' . $escape($ticket["priority"]) . '</td></tr>
            <tr><td><span class="label">Submitted</span>' . $escape($formatDate((string)$ticket["created_at"])) . '</td><td><span class="label">Resolved / closed</span>' . $escape($formatDate($completedAt)) . '</td></tr>
            <tr><td><span class="label">Receipt reference</span>' . $escape($receiptReference) . '</td><td></td></tr>
        </table>
        <div class="description"><p class="description-title">Request summary</p>' . nl2br($escape($ticket["description"])) . '</div>
        <table class="authorization"><tr>
            <td style="width: 27%; text-align: center"><img class="stamp" src="' . $stampImage . '" alt="Samadhan authorization stamp"></td>
            <td style="width: 30%; text-align: center"><img class="completed-stamp" src="' . $completedStampImage . '" alt="Ticket completed"></td>
            <td style="width: 43%; text-align: center"><img class="signature" src="' . $signatureImage . '" alt="Parshva Shah signature"><br>Parshva Shah<br><span class="signoff-title">Authorized representative</span></td>
        </tr></table>
        <div class="footer">Keep this resolution receipt for your records. Generated ' . $escape(date("M j, Y g:i A")) . '.</div>
    </body></html>';

    $options = new Options();
    $options->set("isRemoteEnabled", false);
    $options->set("defaultFont", "DejaVu Sans");
    $pdf = new Dompdf($options);
    $pdf->loadHtml($html, "UTF-8");
    $pdf->setPaper("A4", "portrait");
    $pdf->render();

    $temporaryPath = tempnam(sys_get_temp_dir(), "samadhan-ticket-receipt-");
    if ($temporaryPath === false) {
        throw new RuntimeException("Unable to allocate a temporary receipt file.");
    }

    if (file_put_contents($temporaryPath, $pdf->output(), LOCK_EX) === false) {
        @unlink($temporaryPath);
        throw new RuntimeException("Unable to write the ticket receipt PDF.");
    }

    return $temporaryPath;
}
