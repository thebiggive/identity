<?php

declare(strict_types=1);

namespace BigGive\Identity\Application\Actions\User;

use Psr\Log\LoggerInterface;
use BigGive\Identity\Application\Actions\Action;
use BigGive\Identity\Repository\PersonRepository;

abstract class UserAction extends Action
{
    protected PersonRepository $userRepository;

    public function __construct(LoggerInterface $logger, PersonRepository $userRepository)
    {
        parent::__construct($logger);
        $this->userRepository = $userRepository;
    }
}
