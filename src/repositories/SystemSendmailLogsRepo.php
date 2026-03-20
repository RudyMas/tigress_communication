<?php

namespace Repository;

use Throwable;
use Tigress\Repository;

/**
 * Class SystemSendmailLogsRepo
 */
class SystemSendmailLogsRepo extends Repository
{
    /**
     * Initialize the repository
     * @throws Throwable
     */
    public function __construct()
    {
        $this->dbName = 'default';
        $this->table = 'system_sendmail_logs';
        $this->primaryKey = ['id'];
        $this->model = 'DefaultModel';
        $this->autoload = true;
        $this->createTable = [
            'table' => "
                CREATE TABLE {$this->table} (
                  `id` int(11) NOT NULL,
                  `recipient` varchar(100) NOT NULL,
                  `subject` varchar(100) NOT NULL,
                  `nr_co_account` int(11) NOT NULL,
                  `pwd_web_services` varchar(100) NOT NULL,
                  `error_message` varchar(100) NOT NULL,
                  `send_on` datetime NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
            ",
            'indexes' => [
                "ALTER TABLE {$this->table} ADD PRIMARY KEY (`id`) USING BTREE;",
                "ALTER TABLE {$this->table} MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;"
            ]
        ];
        parent::__construct();
    }
}