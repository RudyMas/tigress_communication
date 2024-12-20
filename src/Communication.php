<?php

namespace Tigress;

/**
 * Class Communication (PHP version 8.4)
 *
 * @author       Rudy Mas <rudy.mas@rudymas.be>
 * @copyright    2024, Rudy Mas (http://rudymas.be/)
 * @license      https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version      2024.12.20.0
 * @package      Tigress
 */
class Communication
{
    /**
     * Get the version of the class.
     *
     * @return array
     */
    public static function version(): array
    {
        return [
            'Communicator' => '2024.12.20',
            'Email' => Email::version(),
        ];
    }
}