<?php

namespace Tigress;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class MicrosoftGraph (PHP version 8.4)
 *
 * @author       Rudy Mas <rudy.mas@rudymas.be>
 * @copyright    2025, Rudy Mas (http://rudymas.be/)
 * @license      https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version      2025.09.10.0
 * @package      Tigress
 */
class MicrosoftGraph
{
    public function __construct(
        private readonly string  $tenantId = '',
        private readonly string  $clientId = '',
        private readonly string  $clientSecret = '',
        private ?LoggerInterface $logger = null,
        private ?string $accessToken = null,
        private Client  $httpClient = new Client()
    )
    {
        TRANSLATIONS->load(SYSTEM_ROOT . '/vendor/tigress/communication/translations/translations.json');

        if ($this->tenantId === '') {
            throw new InvalidArgumentException('tenantId ' . __('cannot be empty.'));
        }
        if ($this->clientId === '') {
            throw new InvalidArgumentException('clientId ' . __('cannot be empty.'));
        }
        if ($this->clientSecret === '') {
            throw new InvalidArgumentException('clientSecret ' . __('cannot be empty.'));
        }
    }

    /**
     * Get the version of the MicrosoftGraph
     *
     * @return string
     */
    public static function version(): string
    {
        return '2025.09.10';
    }

    /**
     * Add an event to the user's calendar.
     *
     * Structure of $event:
     * [
     *   "subject" => "Event Title",
     *   "body" => [
     *     "contentType" => "HTML",
     *     "content" => "Event Description"
     *   ],
     *   "start" => [
     *     "dateTime" => "2025-01-01T10:00:00",
     *     "timeZone" => "UTC"
     *   ],
     *   "end" => [
     *     "dateTime" => "2023-10-01T11:00:00",
     *     "timeZone" => "UTC"
     *   ],
     *   "categories" => ["Category1", "Category2"],
     *   "allowNewTimeProposals" => true/false,
     *   "isOnlineMeeting" => true/false,
     *   "responseRequested" => true/false,
     *   "location" => [
     *     "displayName" => "Location Name"
     *   ],
     *   "attendees" => [
     *     [
     *       [
     *         "emailAddress" => [
     *           "address" => "email.address@example.com",
     *           "name" => "Recipient Name"
     *         ],
     *         "type" => "required"
     *       ],
     *       [
     *         "emailAddress" => [
     *           "address" => "email.address@example.com",
     *           "name" => "Recipient Name"
     *         ],
     *         "type" => "required"
     *       ]
     *     ]
     *   ]
     * ]
     *
     * @param string $locationAddress
     * @param array $event
     * @return array
     * @throws GuzzleException
     */
    public function addEvent(string $locationAddress, array $event): array
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        $response = $this->httpClient->post("https://graph.microsoft.com/v1.0/users/{$locationAddress}/calendar/events", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($event),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Delete an event from the user's calendar by its iCalUId.
     *
     * @param string $locationAddress
     * @param string $iCalUId
     * @return void
     * @throws GuzzleException
     */
    public function deleteEvent(string $locationAddress, string $iCalUId): void
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        $eventId = $this->getEventIdByICalUId($locationAddress, $iCalUId);

        $this->httpClient->delete("https://graph.microsoft.com/v1.0/users/{$locationAddress}/events/{$eventId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * Check if an event exists in the user's calendar by its iCalUId.
     *
     * @param string $locationAddress
     * @param string $iCalUId
     * @return bool
     * @throws GuzzleException
     */
    public function eventExists(string $locationAddress, string $iCalUId): bool
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        try {
            $this->getEventIdByICalUId($locationAddress, $iCalUId);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Check if a location is free between two date/times.
     *
     * @param array $locationAddresses
     * @param string $startDateTime
     * @param string $endDateTime
     * @param string $timeZone
     * @return bool
     * @throws GuzzleException
     */
    public function isLocationFree(array $locationAddresses, string $startDateTime, string $endDateTime, string $timeZone = 'UTC'): bool
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        $response = $this->httpClient->post("https://graph.microsoft.com/v1.0/users/{$locationAddresses[0]}/calendar/getSchedule", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                "schedules" => $locationAddresses,
                "startTime" => [
                    "dateTime" => date('Y-m-d\TH:i:s', strtotime($startDateTime) + 60),
                    "timeZone" => $timeZone
                ],
                "endTime" => [
                    "dateTime" => date('Y-m-d\TH:i:s', strtotime($endDateTime) - 60),
                    "timeZone" => $timeZone
                ],
                "availabilityViewInterval" => 15
            ]),
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (isset($data['value'][0]['scheduleItems']) && count($data['value'][0]['scheduleItems']) > 0) {
            return false;
        }

        return true;
    }

    /**
     * List events in the user's calendar between two date/times.
     *
     * @param string $locationAddress
     * @param string $startDateTime
     * @param string $endDateTime
     * @param string $timeZone
     * @return array
     * @throws GuzzleException
     */
    public function listEvents(string $locationAddress, string $startDateTime, string $endDateTime, string $timeZone = 'UTC'): array
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        $response = $this->httpClient->get("https://graph.microsoft.com/v1.0/users/{$locationAddress}/calendar/events", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'query' => [
                'startDateTime' => date('Y-m-d\TH:i:s', strtotime($startDateTime)),
                'endDateTime' => date('Y-m-d\TH:i:s', strtotime($endDateTime)),
                '$orderby' => 'start/dateTime',
                '$top' => 100
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $data['value'] ?? [];
    }

    /**
     * Update an event in the user's calendar by its iCalUId.
     *
     * Structure of $event is the same as in addEvent method.
     *
     * @param string $locationAddress
     * @param string $iCalUId
     * @param array $event
     * @return array
     * @throws GuzzleException
     */
    public function updateEvent(string $locationAddress, string $iCalUId, array $event): array
    {
        if ($this->accessToken === null) {
            $this->authenticate();
        }

        $eventId =$this->getEventIdByICalUId($locationAddress, $iCalUId);

        $response = $this->httpClient->patch("https://graph.microsoft.com/v1.0/users/{$locationAddress}/events/{$eventId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($event),
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Authenticate and obtain an access token from Microsoft Graph.
     *
     * @return void
     * @throws GuzzleException
     */
    private function authenticate(): void
    {
        $response = $this->httpClient->get("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents());
        if (isset($data->access_token)) {
            $this->accessToken = $data->access_token;
        } else {
            $this->logger?->critical(__('Failed to obtain access token from Microsoft Graph.'));
            throw new InvalidArgumentException(__('Failed to obtain access token from Microsoft Graph.'));
        }
    }

    /**
     * Get the event ID by its iCalUId.
     *
     * @param string $userEmail
     * @param string $iCalUId
     * @return mixed
     * @throws GuzzleException
     */
    private function getEventIdByICalUId(string $userEmail, string $iCalUId): mixed
    {
        $response = $this->httpClient->get("https://graph.microsoft.com/v1.0/{$userEmail}/events", [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ],
            'query' => [
                '$filter' => "iCalUId eq '{$iCalUId}'"
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (isset($data['value'][0]['id'])) {
            return $data['value'][0]['id'];
        } else {
            $this->logger?->critical(__('Event not found. iCalUId: ') . $iCalUId);
            throw new InvalidArgumentException(__('Event not found. iCalUId: ') . $iCalUId);
        }

    }

    /**
     * Set the access token to be used for requests.
     *
     * @param string|null $accessToken
     * @return void
     */
    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * Set the logger to be used.
     *
     * @param LoggerInterface|null $logger
     * @return void
     */
    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}