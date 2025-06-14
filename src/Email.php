<?php

namespace Tigress;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class Email (PHP version 8.4)
 *
 * @author       Rudy Mas <rudy.mas@rudymas.be>
 * @copyright    2024-2025, Rudy Mas (http://rudymas.be/)
 * @license      https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version      2025.06.13.1
 * @package      Tigress
 */
class Email
{
    public PHPMailer $mail;

    /**
     * Get the version of the Email
     *
     * @return string
     */
    public static function version(): string
    {
        return '2025.06.13';
    }

    public function __construct($exceptions = null)
    {
        $this->mail = new PHPMailer($exceptions);
        $this->mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
    }

    /**
     * Close the SMTP connection
     */
    public function __destruct()
    {
        $this->mail->smtpClose();
    }

    /**
     * Setup mail
     *
     * - SMTPSecure: PHPMailer::ENCRYPTION_STARTTLS (587) or PHPMailer::ENCRYPTION_SMTPS (465)
     *
     * @param string $host
     * @param bool $SMTPAuth
     * @param string $username
     * @param string $password
     * @param int $port
     * @param string $SMTPSecure
     * @return void
     */
    public function setupMail(
        string $host,
        bool $SMTPAuth,
        string $username,
        string $password,
        int $port = 587,
        string $SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS
    ): void
    {
        $this->mail->isSMTP();
        $this->mail->Host = $host;
        if ($SMTPAuth) {
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $username;
            $this->mail->Password = $password;
        }
        $this->mail->SMTPSecure = $SMTPSecure;
        $this->mail->Port = $port;
    }

    /**
     * Send an email
     *
     * - from: ['email' => 'email', 'name' => 'name'] or ['email' => 'email']
     * - to: [['email' => 'email', 'name' => 'name'], ...] or [['email' => 'email'], ...]
     * - subject: 'subject'
     * - body: 'html/plain text'
     * - test: true/false (send to rudy.mas@go-next.be)
     * - testTo: ['email' => 'email', 'name' => 'name']
     * - isHtml: true/false
     * - cc: [['email' => 'email', 'name' => 'name'], ...] or [['email' => 'email'], ...]
     * - bcc: [['email' => 'email', 'name' => 'name'], ...] or [['email' => 'email'], ...]
     *
     * @param array $from
     * @param array $to
     * @param string $subject
     * @param string $body
     * @param bool $test
     * @param array $testTo
     * @param bool $isHtml
     * @param array $cc
     * @param array $bcc
     * @return bool
     * @throws Exception
     */
    public function sendMail(
        array  $from,
        array  $to,
        string $subject,
        string $body,
        bool   $test = false,
        array  $testTo = ['email' => 'rudy.mas@rudymas.be', 'name' => 'Rudy Mas'],
        bool   $isHtml = true,
        array  $cc = [],
        array  $bcc = []
    ): bool
    {
        $this->mail->isHTML($isHtml);
        $this->mail->CharSet = 'UTF-8';
        $this->mail->setFrom($from['email'], $from['name'] ?? 'Tigress Mailer');

        if ($test) {
            $this->mail->addAddress($testTo['email'], $testTo['name'] ?? $testTo['email']);
        } else {
            foreach ($to as $email) {
                $this->mail->addAddress($email['email'], $email['name'] ?? $email['email']);
            }
        }

        foreach ($cc as $email) {
            $this->mail->addCC($email['email'], $email['name'] ?? $email['email']);
        }

        foreach ($bcc as $email) {
            $this->mail->addBCC($email['email'], $email['name'] ?? $email['email']);
        }

        $this->mail->Subject = $subject;
        $this->mail->Body = $body;
        return $this->mail->send();
    }

    /**
     * Add an attachment to the email
     *
     * @throws Exception
     */
    public function addAttachment(
        string $path,
        string $name = '',
        string $encoding = 'base64',
        string $type = '',
        string $disposition = 'attachment'
    ): void
    {
        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }

        $file = $path . $name;
        $this->mail->addAttachment($file, $name, $encoding, $type, $disposition);
    }

    /**
     * Add an embedded image to the email
     *
     * @throws Exception
     */
    public function addEmbeddedImage(
        string $path,
        string $cid,
        string $name = '',
        string $encoding = 'base64',
        string $type = '',
        string $disposition = 'inline'
    ): void
    {
        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }

        $file = $path . $name;
        $this->mail->addEmbeddedImage($file, $cid, $name, $encoding, $type, $disposition);
    }

    /**
     * Add an ICS file to the email
     * This method creates an ICS calendar event and attaches it to the email.
     * The ICS data should be provided in an associative array format with the following keys:
     * - uid: Unique identifier for the event
     * - sequence: Sequence number for the event (used for updates)
     * - dtstart: Start date and time of the event in 'YYYYMMDDTHHMMSS' format
     * - dtend: End date and time of the event in 'YYYYMMDDTHHMMSS' format
     * - summary: Summary or title of the event
     * - location: Location of the event
     * - description: Description of the event
     * - cn_organizer: Name of the organizer
     * - email_organizer: Email address of the organizer
     * - cn_attendee: Name of the attendee
     * - email_attendee: Email address of the attendee
     *
     * @param array $icsData
     * @param string $filename
     * @return void
     * @throws Exception
     */
    public function addIcs(
        array $icsData,
        string $filename = 'event.ics',
    ): void
    {
        $icsDataDefaults = [
            'uid' => uniqid() . '@' . $_SERVER['SERVER_NAME'],
            'sequence' => 0,
            'dtstamp' => gmdate('Ymd\THis\Z'),
            'dtstart' => gmdate('Ymd\THis', strtotime('+1 hour')),
            'dtend' => gmdate('Ymd\THis', strtotime('+2 hour')),
            'summary' => 'Event',
            'location' => '',
            'description' => '',
            'cn_organizer' => '',
            'email_organizer' => '',
            'cn_attendee' => '',
            'email_attendee' => '',
        ];

        $icsData['description'] = $this->escapeIcsText($icsData['description']);
        $icsData['summary'] = $this->escapeIcsText($icsData['summary']);
        $icsData['location'] = $this->escapeIcsText($icsData['location']);

        // Merge provided ICS data with defaults
        $icsData = array_merge($icsDataDefaults, $icsData);

        $icsString = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Guna Agenda//EN
METHOD:REQUEST
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:{$icsData['uid']}
SEQUENCE:{$icsData['sequence']}
DTSTAMP:{$icsData['dtstamp']}
DTSTART;TZID=Europe/Brussels:{$icsData['dtstart']}
DTEND;TZID=Europe/Brussels:{$icsData['dtend']}
SUMMARY:{$icsData['summary']}
LOCATION:{$icsData['location']}
DESCRIPTION:{$icsData['description']}
ORGANIZER;CN={$icsData['cn_organizer']}:MAILTO:{$icsData['email_organizer']}
ATTENDEE;CN={$icsData['cn_attendee']};RSVP=TRUE:MAILTO:{$icsData['email_attendee']}
END:VEVENT
END:VCALENDAR
ICS;

        $this->mail->addStringAttachment($icsString, $filename, 'base64', 'text/calendar; method=REQUEST; charset=UTF-8');
    }

    /**
     * Escape text for ICS format
     * This method escapes special characters in the text to ensure it is compliant with the ICS format.
     * It replaces the following characters:
     * - Backslash (\) with double backslash (\\\\)
     * - Comma (,) with escaped comma (\\,)
     * - Semicolon (;) with escaped semicolon (\\;)
     * - Colon (:) with escaped colon (\\:)
     * - Newline (\n) with escaped newline (\\n)
     * - Carriage return (\r) with escaped carriage return (\\r)
     *
     * @param string $text
     * @return string
     */
    private function escapeIcsText(string $text): string
    {
        // Escape special characters for ICS format
        return str_replace(
            ['\\', ',', ';', ':', '\n', '\r'],
            ['\\\\', '\\,', '\\;', '\\:', '\\n', '\\r'],
            $text
        );
    }
}