<?php

declare(strict_types=1);

namespace BigGive\Identity\Tests;

use BigGive\Identity\Client\Mailer;
use BigGive\Identity\Domain\Person;
use BigGive\Identity\Repository\PersonRepository;
use BigGive\Identity\Tests\TestCase;
use Prophecy\Argument;

class NameValidationTest extends TestCase
{
    /**
     * @dataProvider allowedAndNotAllowedNamesProvider
     */
    public function testnameValidation(string $purportedName, bool $shouldBeAllowed): void
    {
        $this->assertSame($this->validationFunction($purportedName), $shouldBeAllowed);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function allowedAndNotAllowedNamesProvider(): array
    {
        return [
                    // purported name, should be considered valid
            'Almost too long' => [str_repeat('a', 35), true],
            'Chinese' => ['伟', true],
            'English' => ['Fred', true],
            'French' => ['Bénédicte', true],
            'With Curly Apostrophe' => ['O’Brian', true],
            'With Space' => ['Fred Fred', true],
            'With Straight Apostrophe' => ['O\'Brian', true],
            'hyphenated' => ['Mary-louise', true],
            'hyphenated with real hyphen' => ['Mary‐louise', true],

            'Emoji only' => ['🤷', false],
            'French with emoji' => ['Bénédicte🤷', false],
            'Too long' => [str_repeat('a', 36), false],
            'With brackets' => ['Goody (two)', false],
            'With number' => ['Goody 2', false],
            'empty' => ['', false],
            'space only' => ['  ', false],
            'with ampersand' => ['Mary & Louise', false],
        ];
    }

    private function validationFunction(string $purportedName): bool
    {
        $purportedName = trim($purportedName);

        if (strlen($purportedName) > 35 || strlen($purportedName) < 1) {
            return false;
        }

        // \pL means any "letter", i.e. a character who's unicode "General Category" is any form of "Letter". See
        // https://www.unicode.org/versions/Unicode14.0.0/ch04.pdf

        return preg_match('/^[\pL \'’‐-]*$/u', $purportedName) === 1;
    }
}
