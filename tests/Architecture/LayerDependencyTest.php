<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

final class LayerDependencyTest
{
    // A whitelist: a new package or layer is forbidden in Domain without touching this rule
    public function test_domain_depends_only_on_itself_and_php(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Domain'))
            ->canOnlyDependOn()
            ->classes(
                Selector::inNamespace('App\Domain'),
                Selector::isStandardClass(),
            )
            ->because('L1: Domain holds value objects, interfaces and enums only');
    }

    public function test_application_does_not_depend_on_infrastructure(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNotDependOn()
            ->classes(Selector::inNamespace('App\Infrastructure'))
            ->because('L2: dependencies point inward');
    }

    // Intervention reads and writes files by path, so it stays behind ImageResizer in Infrastructure
    public function test_application_reaches_the_outside_world_only_through_adapters(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App\Application'))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace('Aws'),
                Selector::inNamespace('Bramus'),
                Selector::inNamespace('GuzzleHttp'),
                Selector::inNamespace('Intervention'),
                Selector::inNamespace('Kunnu'),
                Selector::inNamespace('Nyholm'),
                Selector::inNamespace('Psr\Http'),
            )
            ->because('L3a: files, network and environment go through adapters');
    }
}
