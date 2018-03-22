<?php

namespace Oliverde8\Component\PhpEtl\Item;

/**
 * Class ChainBreakItem
 *
 * @author    de Cramer Oliver<oliverde8@gmail.com>
 * @copyright 2018 Oliverde8
 * @package Oliverde8\Component\PhpEtl\Item
 */
class ChainBreakItem implements ItemInterface
{

    public function getSignal(): string
    {
        return 'chainBreak';
    }
}