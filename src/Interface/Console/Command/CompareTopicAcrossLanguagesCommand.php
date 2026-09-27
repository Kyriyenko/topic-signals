<?php

declare(strict_types=1);

namespace TopicSignals\Interface\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TopicSignals\Application\Dto\CompareTopicAcrossLanguagesRequest;
use TopicSignals\Application\UseCase\CompareTopicAcrossLanguages;
use TopicSignals\Interface\Console\JsonResult;

#[AsCommand(name: 'topic:compare-languages', description: 'Compare interest in one topic across several Wikipedia language editions')]
final class CompareTopicAcrossLanguagesCommand extends Command
{
    public function __construct(private readonly CompareTopicAcrossLanguages $useCase)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Free-text topic, e.g. "Intermittent fasting"')
            ->addOption('languages', null, InputOption::VALUE_REQUIRED, 'Comma-separated Wikipedia language codes, e.g. "pl,cs"')
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'Lookback period in months', '24')
            ->addOption('chart', null, InputOption::VALUE_NEGATABLE, 'Generate a PNG comparison chart', true)
            ->addOption('report', null, InputOption::VALUE_NEGATABLE, 'Generate a one-page PDF report', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $languages = array_map('trim', explode(',', (string) $input->getOption('languages')));

            $response = $this->useCase->execute(new CompareTopicAcrossLanguagesRequest(
                topic: (string) $input->getOption('topic'),
                languages: $languages,
                periodMonths: (int) $input->getOption('months'),
                generateChart: (bool) $input->getOption('chart'),
                generateReport: (bool) $input->getOption('report'),
            ));

            return JsonResult::success($output, $response->toArray());
        } catch (\Throwable $e) {
            return JsonResult::error($output, $e);
        }
    }
}
