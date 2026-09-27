<?php

declare(strict_types=1);

namespace TopicSignals\Interface\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TopicSignals\Application\Dto\AssessTopicGrowthRequest;
use TopicSignals\Application\UseCase\AssessTopicGrowth;
use TopicSignals\Interface\Console\JsonResult;

#[AsCommand(name: 'topic:assess-growth', description: 'Assess whether interest in a topic is growing in one Wikipedia language edition')]
final class AssessTopicGrowthCommand extends Command
{
    public function __construct(private readonly AssessTopicGrowth $useCase)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Free-text topic, e.g. "Astronomy"')
            ->addOption('language', null, InputOption::VALUE_REQUIRED, 'Wikipedia language code, e.g. "uk"')
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'Lookback period in months', '24')
            ->addOption('chart', null, InputOption::VALUE_NEGATABLE, 'Generate a PNG trend chart', true)
            ->addOption('report', null, InputOption::VALUE_NEGATABLE, 'Generate a one-page PDF report', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $response = $this->useCase->execute(new AssessTopicGrowthRequest(
                topic: (string) $input->getOption('topic'),
                language: (string) $input->getOption('language'),
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
