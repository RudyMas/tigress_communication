<?php

namespace Tigress;

/**
 * Class Communication (PHP version 8.4)
 *
 * @author       Rudy Mas <rudy.mas@rudymas.be>
 * @copyright    2024-2025, Rudy Mas (http://rudymas.be/)
 * @license      https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version      2025.05.13.1
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
            'Communication' => '2025.05.13',
            'Email' => Email::version(),
            'Smartschool' => Smartschool::version(),
        ];
    }
}