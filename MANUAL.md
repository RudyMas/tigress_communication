# Tigress Communication Library — Programmer's Manual

## Overview

`tigress/communication` is a PHP 8.5 library that provides three communication channels:

- **Email** — Send HTML/plain-text emails with attachments, embedded images, and ICS calendar invites via SMTP (powered by PHPMailer).
- **Microsoft Graph** — Manage Exchange Online calendar events (CRUD, free/busy queries) via the Microsoft Graph API.
- **Smartschool** — Send internal platform messages via Smartschool's SOAP web service (V3).

It depends on the `tigress/core` framework for configuration (`CONFIG`, `TRANSLATIONS`, `SYSTEM_ROOT`) and a database-backed repository for Smartschool send-logging.

---

## Installation

```bash
composer require tigress/communication
```

**Requirements:**
- PHP >= 8.5
- ext-soap
- guzzlehttp/guzzle >= 7
- phpmailer/phpmailer >= v6.9
- tigress/core (dev-master, dev dependency)

---

## Global Dependencies

The library relies on several constants/globals defined by `tigress/core`:

| Constant / Global  | Purpose |
|-------------------|---------|
| `SYSTEM_ROOT`     | Absolute path to your application root |
| `TRANSLATIONS`    | Translation loader object (call `->load(...)` then use `__(...)`) |
| `CONFIG`          | Config object; `CONFIG->smartschool` holds optional test-user defaults |
| `Server`          | (implied) Used for `$_SERVER['SERVER_NAME']` in ICS UID generation |

---

## 1. Email Class

Namespace: `Tigress\Email`

Wraps PHPMailer to send emails via SMTP.

### Constructor

```php
$email = new \Tigress\Email(?bool $exceptions = null);
```

### Configuration

```php
$email->setupMail(
    string $host,
    bool $SMTPAuth,
    string $username,
    string $password,
    int $port = 587,
    string $SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS // or ENCRYPTION_SMTPS (465)
);
```

### Sending Email

```php
$email->sendMail(
    array  $from,        // ['email' => '...', 'name' => '...']  (name is optional)
    array  $to,          // [['email' => '...', 'name' => '...'], ...]
    string $subject,
    string $body,        // HTML or plain text
    bool   $test = false, // when true, redirects to $testTo address
    array  $testTo = ['email' => 'rudy.mas@rmsoft.be', 'name' => 'Rudy Mas'],
    bool   $isHtml = true,
    array  $cc = [],     // same structure as $to
    array  $bcc = []     // same structure as $to
): bool;
```

### Attachments

```php
$email->addAttachment(string $path, string $name, string $encoding = 'base64', string $type = '', string $disposition = 'attachment');

$email->addEmbeddedImage(string $path, string $cid, string $name = '', string $encoding = 'base64', string $type = '', string $disposition = 'inline');
```

### ICS Calendar Attachments

```php
$email->addIcs(
    array $icsData,
    string $filename = 'event.ics'
);
```

**`$icsData` fields:**

| Key              | Required | Default |
|------------------|----------|---------|
| `uid`            | no       | `uniqid() . '@' . $_SERVER['SERVER_NAME']` |
| `sequence`       | no       | `0` |
| `dtstamp`        | no       | current UTC time |
| `dtstart`        | no       | now + 1h (UTC) |
| `dtend`          | no       | now + 2h (UTC) |
| `timezone`       | no       | `Europe/Brussels` |
| `summary`        | no       | `Event` |
| `location`       | no       | `''` |
| `description`    | no       | `''` |
| `cn_organizer`   | no       | `''` |
| `email_organizer`| no       | `''` |
| `cn_attendee`    | no       | `''` |
| `email_attendee` | no       | `''` |

Only non-empty values need to be provided; missing keys fall back to defaults.

### Example

```php
$mail = new \Tigress\Email();
$mail->setupMail('smtp.example.com', true, 'user', 'pass', 587);
$mail->sendMail(
    ['email' => 'noreply@example.com', 'name' => 'System'],
    [['email' => 'user@example.com', 'name' => 'User']],
    'Hello',
    '<h1>World</h1>'
);
```

---

## 2. Microsoft Graph Class

Namespace: `Tigress\MicrosoftGraph`

Manages Exchange Online calendar events using the Microsoft Graph API v1.0 with the OAuth2 client-credentials flow.

### Constructor

```php
$graph = new \Tigress\MicrosoftGraph(
    string  $tenantId = '',
    string  $clientId = '',
    string  $clientSecret = '',
    ?LoggerInterface $logger = null,
    ?string $accessToken = null,
    Client  $httpClient = new Client()
);
```

All three credentials (`tenantId`, `clientId`, `clientSecret`) are mandatory and throw `InvalidArgumentException` if empty.

### Authentication

Authentication is automatic on demand — the first API call triggers `POST` to `https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token`. You may also pre-set a token:

```php
$graph->setAccessToken(?string $accessToken);
```

### Calendar Methods

**Add Event:**

```php
$graph->addEvent(string $locationAddress, array $event): array;
```

`$locationAddress` is the user's email (UPN). `$event` follows the [Microsoft Graph event schema](https://learn.microsoft.com/en-us/graph/api/user-post-events). Key structure:

```php
[
    'subject'              => 'Meeting',
    'body'                 => ['contentType' => 'HTML', 'content' => '...'],
    'start'                => ['dateTime' => '2025-01-01T10:00:00', 'timeZone' => 'UTC'],
    'end'                  => ['dateTime' => '2025-01-01T11:00:00', 'timeZone' => 'UTC'],
    'categories'           => ['Label'],
    'allowNewTimeProposals'=> true,
    'isOnlineMeeting'      => true,
    'responseRequested'    => true,
    'location'             => ['displayName' => 'Room A'],
    'attendees'            => [
        ['emailAddress' => ['address' => 'a@b.com', 'name' => 'A'], 'type' => 'required'],
    ],
];
```

**Update Event:**

```php
$graph->updateEvent(string $locationAddress, string $iCalUId, array $event): array;
```

Looks up the event by its `iCalUId` and applies a PATCH.

**Delete Event:**

```php
$graph->deleteEvent(string $locationAddress, string $iCalUId): void;
```

**Check if Event Exists:**

```php
$graph->eventExists(string $locationAddress, string $iCalUId): bool;
```

**List Events in a Date Range:**

```php
$graph->listEvents(string $locationAddress, string $startDateTime, string $endDateTime, string $timeZone = 'UTC'): array;
```

Returns up to 100 events ordered by start time.

**Check Location Availability:**

```php
$graph->isLocationFree(array $locationAddresses, string $startDateTime, string $endDateTime, string $timeZone = 'UTC'): bool;
```

Returns `true` if the schedule is empty (no conflicts). The first address in the array is used as the calendar owner for the `getSchedule` call.

### Logging

```php
$graph->setLogger(?LoggerInterface $logger);
```

---

## 3. Smartschool Class

Namespace: `Tigress\Smartschool`

Sends internal platform messages through Smartschool's SOAP web service (V3). The class auto-logs every send attempt into the `system_sendmail_logs` database table.

### Constructor

```php
$ss = new \Tigress\Smartschool(
    string $platform,          // e.g. 'school.example.be'
    ?string $passwordWebServices = null,
    ?LoggerInterface $logger = null
);
```

Throws `Exception` if the SOAP connection fails.

### Configuration

The constructor reads `CONFIG->smartschool->testUser` if available:

```json
{
  "smartschool": {
    "testUser": {
      "platform": "...",
      "webservicespwd": "...",
      "username": "...",
      "nrCoAccount": 0
    }
  }
}
```

You can also set the test user programmatically:

```php
$ss->setTestUser([
    'platform' => 'school.example.be',
    'webservicespwd' => 'secret',
    'username' => 'testuser',
    'nrCoAccount' => 0
]);
```

### Sending Mail

```php
$ss->sendMail(
    string $recipient,
    string $subject,
    string $body,
    int $coAccount,
    ?array $attachments = null,
    ?string $redirectByError = '/',
    bool $debug = false,
    string $errorMsg = ''   // defaults to a translated fallback
): void;
```

- `$debug = true` — redirects the message to the configured test user instead of the real recipient.
- `$redirectByError` — on failure, performs an HTTP redirect to this URL (unless running in CLI or headers already sent).
- `$attachments` — array of attachment data as expected by the Smartschool SOAP API.

### Password Management

```php
$ss->getPasswordWebServices(): string;
$ss->setPasswordWebServices(string $passwordWebServices): void;
```

### SOAP Client Access

```php
$ss->getSoap(): SoapClient;
```

### Example

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Tigress\Smartschool;

$logger = new Logger('smartschool');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/smartschool.log', \Monolog\Level::Error));

$ss = new Smartschool('school.example.be', 'supersecret', $logger);
$ss->sendMail('student@school.be', 'Reminder', 'Your assignment is due.', 12345);
```

---

## 4. Communication Facade

Namespace: `Tigress\Communication`

A static utility to report version information for all sub-components:

```php
$versions = \Tigress\Communication::version();
// [
//     'Communication' => '2026.03.20',
//     'Email'         => '2025.12.09',
//     'MicrosoftGraph' => '2025.12.09',
//     'Smartschool'   => '2026.03.20',
// ]
```

---

## 5. Translations

The translations file is at `vendor/tigress/communication/translations/translations.json` and is automatically loaded by each class's constructor. Supported locales: **nl**, **fr**, **de**, **es**, **it**, **sv**.

Use the `__(...)` helper function (provided by `tigress/core`) to retrieve translated strings.

---

## 6. Database: `system_sendmail_logs` Table

Created automatically by `Repository\SystemSendmailLogsRepo` (when the parent `Repository` auto-creates tables).

| Column               | Type         | Notes       |
|----------------------|--------------|-------------|
| `id`                 | int(11)      | PK, AUTO_INCREMENT |
| `recipient`          | varchar(100) |             |
| `subject`            | varchar(100) |             |
| `nr_co_account`      | int(11)      |             |
| `pwd_web_services`   | varchar(100) |             |
| `error_message`      | varchar(100) |             |
| `send_on`            | datetime     |             |

---

## 7. Code Review Notes

### Strengths
- **PSR-4 autoloading** with clear namespace separation.
- **Constructor promotion** and `readonly` properties (PHP 8.5+).
- **PSR-3 LoggerInterface** support throughout — easy to integrate with Monolog or any PSR-3 logger.
- **Multi-language translations** via a single JSON file with 6 locales.
- **Safe defaults** for ICS generation and mail parameters.
- **Explicit authentication validation** in `MicrosoftGraph` constructor — fails fast on missing credentials.
