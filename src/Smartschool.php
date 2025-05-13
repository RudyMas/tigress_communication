<?php

namespace Tigress;

use Exception;
use Repository\SystemSendmailLogsRepo;
use SoapClient;
use SoapFault;
use Psr\Log\LoggerInterface;

/**
 * Class Smartschool (PHP version 8.4)
 *
 * @author       Rudy Mas <rudy.mas@rudymas.be>
 * @copyright    2025, Rudy Mas (http://rudymas.be/)
 * @license      https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version      2025.05.13.3
 * @package      Tigress
 */
class Smartschool
{
    private SoapClient $soap;
    private string $passwordWebServices = '';
    private ?LoggerInterface $logger;
    private array $testUser = [
        'platform' => '',
        'webservicespwd' => '',
        'username' => '',
        'nrCoAccount' => 0
    ];

    /**
     * Get the version of the Email
     *
     * @return string
     */
    public static function version(): string
    {
        return '2025.05.13';
    }

    /**
     * @param string $platform
     * @param string|null $passwordWebServices
     * @param LoggerInterface|null $logger
     * @throws Exception
     */
    public function __construct(string $platform, ?string $passwordWebServices = null, ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        if ($passwordWebServices !== null) {
            $this->passwordWebServices = $passwordWebServices;
        }

        if (isset(CONFIG->smartschool)) {
            $smartschool = CONFIG->smartschool;
            $this->testUser = [
                'platform' => $smartschool->platform ?? '',
                'webservicespwd' => $smartschool->webservicespwd ?? '',
                'username' => $smartschool->username ?? '',
                'nrCoAccount' => $smartschool->nrCoAccount ?? 0
            ];
        }

        $opts = [
            'http' => [
                'user_agent' => 'PHPSoapClient'
            ]
        ];
        $context = stream_context_create($opts);
        $soapClientOptions = [
            'stream_context' => $context,
            'cache_wsdl' => WSDL_CACHE_NONE
        ];

        try {
            $this->soap = new SoapClient('https://' . $platform . '/Webservices/V3?wsdl', $soapClientOptions);
        } catch (SoapFault $e) {
            $this->logger?->error('SOAP-verbinding met Smartschool mislukt.', [
                'platform' => $platform,
                'message' => $e->getMessage(),
                'exception' => $e
            ]);

            throw new Exception('SoapClient error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send mail to SmartSchool (with a debug option)
     *
     * @param string $recipient
     * @param string $subject
     * @param string $body
     * @param int $coAccount
     * @param array|null $attachments
     * @param string|null $redirectByError
     * @param bool $debug
     * @return void
     * @throws Exception
     */
    public function sendMail(
        string $recipient,
        string $subject,
        string $body,
        int $coAccount,
        ?array $attachments = null,
        ?string $redirectByError = '/',
        bool $debug = false
    ): void {
        if ($debug) {
            $this->sendTestMail($subject, $body, $attachments, $redirectByError);
        } else {
            $this->sendMailToSmartSchool($recipient, $subject, $body, $attachments, $coAccount, $redirectByError);
        }
    }

    /**
     * Add a log-entry for the sendmail
     *
     * @param string $recipient
     * @param string $subject
     * @param int $nrCoAccount
     * @param string $errorMessage
     * @return void
     */
    private function sendmailLogging(string $recipient, string $subject, int $nrCoAccount, string $errorMessage): void
    {
        $systemSendmailLogs = new SystemSendmailLogsRepo();
        $systemSendmailLogs->new();
        $systemSendmailLog = $systemSendmailLogs->current();
        $systemSendmailLog->recipient = $recipient;
        $systemSendmailLog->subject = $subject;
        $systemSendmailLog->nr_co_account = $nrCoAccount;
        $systemSendmailLog->pwd_web_services = $this->passwordWebServices;
        $systemSendmailLog->error_message = $errorMessage;
        $systemSendmailLogs->save($systemSendmailLog);
    }

    /**
     * Send actual mail to SmartSchool
     *
     * @param string $recipient
     * @param string $subject
     * @param string $body
     * @param array|null $attachments
     * @param int $coAccount
     * @param string|null $redirectByError
     * @param string $errorMsg
     * @return void
     * @throws Exception
     */
    private function sendMailToSmartSchool(string $recipient, string $subject, string $body, ?array $attachments, int $coAccount, ?string $redirectByError, string $errorMsg = 'An error occurred while sending the message. However, the action was still completed.'): void
    {
        try {
            $result = $this->soap->sendMsg(
                $this->passwordWebServices,
                $recipient,
                $subject,
                $body,
                null,
                $attachments,
                $coAccount
            );

            if ($result != 0) {
                $errorCodes = $this->soap->returnJsonErrorCodes();
                $errorCodes = json_decode($errorCodes);

                $errorMessage = $errorCodes->{$result};
                throw new Exception($errorMessage);
            }

            $this->sendmailLogging($recipient, $subject, $coAccount, 'Mail sent successfully');
            $this->logger?->info('Mail sent successfully', [
                'recipient' => $recipient,
                'subject' => $subject,
                'coAccount' => $coAccount
            ]);
        } catch (Exception $e) {
            $_SESSION['error'] = $errorMsg;
            $this->sendmailLogging($recipient, $subject, $coAccount, $e->getMessage());
            $this->logger?->error($e->getMessage(), [
                'recipient' => $recipient,
                'subject' => $subject,
                'coAccount' => $coAccount,
                'exception' => $e
            ]);
            if ($redirectByError !== null && PHP_SAPI !== 'cli' && !headers_sent()) {
                header('Location: ' . $redirectByError);
            }
        }
    }

    /**
     * Send test mail to SmartSchool
     *
     * @param string $subject
     * @param string $body
     * @param array|null $attachments
     * @param string|null $redirectByError
     * @return void
     * @throws Exception
     */
    private function sendTestMail(string $subject, string $body, ?array $attachments, ?string $redirectByError): void
    {
        if (
            empty($this->testUser['platform']) ||
            empty($this->testUser['webservicespwd']) ||
            empty($this->testUser['username']) ||
            empty($this->testUser['nrCoAccount'])
        ) {
            throw new Exception('Test user information is incomplete. Please configure it using setTestUser().');
        }

        $testSmartSchool = new Smartschool($this->testUser['platform'], $this->testUser['webservicespwd'], $this->logger);
        $testSmartSchool->sendMail(
            $this->testUser['username'],
            'Test: ' . $subject,
            $body,
            $this->testUser['nrCoAccount'],
            $attachments,
            $redirectByError
        );
    }

    /**
     * @return string
     */
    public function getPasswordWebServices(): string
    {
        return $this->passwordWebServices;
    }

    /**
     * @param string $passwordWebServices
     * @return void
     */
    public function setPasswordWebServices(string $passwordWebServices): void
    {
        $this->passwordWebServices = $passwordWebServices;
    }

    /**
     * @return SoapClient
     */
    public function getSoap(): SoapClient
    {
        return $this->soap;
    }

    /**
     * @param array $testUser
     * @return void
     */
    public function setTestUser(array $testUser): void
    {
        $this->testUser = array_merge($this->testUser, $testUser);
    }
}
