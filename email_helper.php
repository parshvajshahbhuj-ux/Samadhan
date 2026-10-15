<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/vendor/autoload.php";

/**
 * Send an HTML email with optional attachments.
 *
 * Each attachment should be:
 * [
 *     "path" => "/absolute/server/path/to/file",
 *     "name" => "original-filename.pdf"
 * ]
 */
function sendHelpDeskEmail($to, $subject, $body, array $attachments = [])
{
    try {
        $mailConfig = require __DIR__ . "/config/mail.php";
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $mailConfig["host"];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig["username"];
        $mail->Password = $mailConfig["password"];
        $mail->SMTPSecure = $mailConfig["encryption"];
        $mail->Port = $mailConfig["port"];

        $mail->setFrom(
            $mailConfig["from_email"],
            $mailConfig["from_name"]
        );

        $mail->addAddress($to);

        $plainBody = trim(preg_replace('/\s+/', ' ', html_entity_decode(
            strip_tags((string)$body),
            ENT_QUOTES | ENT_HTML5,
            "UTF-8"
        )));
        $mail->addEmbeddedImage(
            __DIR__ . "/assets/images/samadhan-logo-email.png",
            "samadhan-logo",
            "samadhan-logo-email.png",
            PHPMailer::ENCODING_BASE64,
            "image/png"
        );

        // Attach files using trusted local paths and safe display names.
        foreach ($attachments as $attachment) {
            $path = $attachment["path"] ?? "";
            $name = $attachment["name"] ?? basename((string)$path);

            if (
                !is_string($path) ||
                $path === "" ||
                !is_file($path) ||
                !is_readable($path)
            ) {
                throw new Exception("Email attachment is missing or unreadable.");
            }

            $safeDisplayName = basename(str_replace("\\", "/", (string)$name));
            $mail->addAttachment($path, $safeDisplayName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = samadhanEmailLayout((string)$body);
        $mail->AltBody = $plainBody;

        $mail->send();
        return true;

    } catch (Throwable $e) {
        $mailerError = isset($mail) ? $mail->ErrorInfo : "";
        error_log("HelpDesk Email Error: " . $mailerError . " | Exception: " . $e->getMessage());
        return false;
    }
}

function samadhanEmailLayout(string $content): string
{
    if (preg_match('/<title>Ticket #\d+ received<\/title>/i', $content)) {
        $content = str_replace(
            "<td style='font-size:22px;line-height:28px;font-weight:bold;letter-spacing:.2px;color:#ffffff;'>Samadhan</td>",
            "<td style='font-size:22px;line-height:28px;font-weight:bold;letter-spacing:.2px;color:#ffffff;'><table role='presentation' cellpadding='0' cellspacing='0' border='0' style='border-collapse:collapse;'><tr><td width='54' valign='middle' style='width:54px;padding-right:12px;'><table role='presentation' cellpadding='0' cellspacing='0' border='0' style='border-collapse:separate;border-spacing:0;background-color:#e4f4f8;border:1px solid #b9e1eb;border-radius:10px;'><tr><td style='padding:3px;'><img src='cid:samadhan-logo' width='42' height='42' alt='Samadhan logo' style='display:block;width:42px;height:42px;border:0;'></td></tr></table></td><td valign='middle' style='font-size:22px;line-height:28px;font-weight:bold;color:#ffffff;'>Samadhan</td></tr></table></td>",
            $content
        );

        return preg_replace(
            '/<\/head>/i',
            "<style type='text/css'>@media only screen and (max-width:520px){.ticket-email-outer{padding:16px 8px!important}.ticket-email-header{padding:19px 18px!important}.ticket-email-content{padding:25px 18px 15px!important}.ticket-email-section{padding-left:18px!important;padding-right:18px!important}.ticket-email-card{border-radius:12px!important}.ticket-email-details-col{display:block!important;width:auto!important;padding:10px 18px 12px!important}.ticket-email-details-table{width:100%!important}.ticket-email-ref{padding:14px 12px!important}.ticket-email-status{padding:14px 12px!important}.ticket-email-title{font-size:22px!important;line-height:28px!important}.ticket-email-brand-label{font-size:9px!important;letter-spacing:.3px!important}}</style></head>",
            $content,
            1
        ) ?? $content;
    }

    if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $content, $matches)) {
        $content = $matches[1];
    } else {
        $content = preg_replace('/<!doctype[^>]*>|<\/?(?:html|head)\b[^>]*>/i', '', $content);
    }

    return "
        <!doctype html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta name='color-scheme' content='light'>
            <meta name='supported-color-schemes' content='light'>
            <title>Samadhan Support</title>
            <style type='text/css'>
                @media only screen and (max-width:520px) {
                    .samadhan-email-outer { padding:16px 8px !important; }
                    .samadhan-email-card { border-radius:12px !important; }
                    .samadhan-email-header { padding:16px 17px !important; }
                    .samadhan-email-content { padding:20px 17px !important; }
                    .samadhan-email-footer { padding:14px 17px !important; }
                    .samadhan-email-brand-name { font-size:20px !important; line-height:25px !important; }
                    .samadhan-email-brand-label { font-size:9px !important; letter-spacing:.3px !important; }
                    .samadhan-email-content img { max-width:100% !important; height:auto !important; }
                    .samadhan-email-content table { max-width:100% !important; }
                    .samadhan-email-content p,
                    .samadhan-email-content h1,
                    .samadhan-email-content h2,
                    .samadhan-email-content div { overflow-wrap:anywhere; }
                }
            </style>
        </head>
        <body style='margin:0;padding:0;background-color:#eaf0f5;font-family:Arial,Helvetica,sans-serif;color:#183247;'>
            <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border-collapse:collapse;background-color:#eaf0f5;'>
                <tr>
                    <td align='center' class='samadhan-email-outer' style='padding:30px 14px;'>
                        <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' class='samadhan-email-card' style='width:100%;max-width:680px;border-collapse:separate;border-spacing:0;background-color:#ffffff;border:1px solid #dce6ed;border-radius:16px;overflow:hidden;'>
                            <tr>
                                <td class='samadhan-email-header' style='padding:19px 26px;background-color:#0b3047;'>
                                    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='border-collapse:collapse;'>
                                        <tr>
                                            <td width='54' valign='middle' style='width:54px;padding-right:12px;'>
                                                <table role='presentation' cellpadding='0' cellspacing='0' border='0' style='border-collapse:separate;border-spacing:0;background-color:#e4f4f8;border:1px solid #b9e1eb;border-radius:10px;'>
                                                    <tr><td style='padding:3px;'><img src='cid:samadhan-logo' width='42' height='42' alt='Samadhan logo' style='display:block;width:42px;height:42px;border:0;'></td></tr>
                                                </table>
                                            </td>
                                            <td valign='middle' class='samadhan-email-brand-name' style='font-size:22px;line-height:28px;font-weight:bold;letter-spacing:.2px;color:#ffffff;'>
                                                Samadhan
                                                <div class='samadhan-email-brand-label' style='padding-top:3px;font-size:11px;line-height:16px;font-weight:normal;letter-spacing:.7px;color:#bfeafa;'>SUPPORT &amp; RESOLUTION DESK</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td class='samadhan-email-content' style='padding:26px 26px 24px;background-color:#ffffff;font-size:15px;line-height:1.65;color:#334e60;'>
                                    {$content}
                                </td>
                            </tr>
                            <tr>
                                <td class='samadhan-email-footer' style='padding:16px 26px;border-top:1px solid #e7eef2;background-color:#f8fafc;'>
                                    <p style='margin:0;font-size:12px;line-height:18px;color:#617887;'>Samadhan Support Team <span style='color:#a1b0b9;'>·</span> Support &amp; Resolution Desk</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
    ";
}
