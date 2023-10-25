<?php

namespace BigGive\Identity\Tests;

use ArrayIterator;
use Stripe\Collection;
use Stripe\StripeObject;

class StripeFormatting
{
    /**
     * @psalm-suppress MissingTemplateParam
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    public static function buildAutoIterableCollection(string $json): Collection
    {
        /** @var \stdClass $itemsArray */
        $itemsArray = json_decode($json, false);
        /** @var StripeObject[] $itemData */
        $itemData = $itemsArray->data;
        return new class ($itemData) extends Collection {
            /**
             * @param StripeObject[] $itemData
             */
            public function __construct(private array $itemData)
            {
                parent::__construct();
            }

            public function autoPagingIterator(): ArrayIterator
            {
                return new ArrayIterator($this->itemData);
            }
        };
    }
}
