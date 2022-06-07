<?php

declare(strict_types=1);

namespace Tbg\Identity\Application\Actions\User;

use Psr\Log\LoggerInterface;
use Tbg\Identity\Application\Actions\Action;
use Tbg\Identity\Repository\UserRepository;

abstract class UserAction extends Action
{
    protected UserRepository $userRepository;

    public function __construct(LoggerInterface $logger, UserRepository $userRepository)
    {
        parent::__construct($logger);
        $this->userRepository = $userRepository;
    }
}
