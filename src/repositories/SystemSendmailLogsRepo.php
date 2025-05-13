<?php

namespace Repository;

use Tigress\Repository;

/**
 * Class RfdsRepo
 */
class SystemSendmailLogsRepo extends Repository
{
    /**
     * Initialize the repository
     */
    public function __construct()
    {
        $this->dbName = 'default';
        $this->table = 'system_sendmail_logs';
        $this->primaryKey = ['id'];
        $this->model = 'DefaultModel';
        $this->autoload = true;
        parent::__construct();
    }
}