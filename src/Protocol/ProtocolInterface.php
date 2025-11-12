<?php

/*
 * This file is part of Tapo PHP.
 *
 * (c) Antal Áron <antalaron@antalaron.hu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Antalaron\Tapo\Protocol;

/**
 * @author Antal Áron <antalaron@antalaron.hu>
 */
interface ProtocolInterface
{
    public function initialize(): void;

    public function request(string $method, ?array $params = null);
}
