<?php

declare(strict_types=1);

namespace App\Command;

use App\Domain\Variant;
use App\Optimizer\Input\InvalidInputException;
use App\Service\OptimizationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:optimize', description: 'Assign tasks to employees (minimum cost or minimum makespan)')]
final class OptimizeCommand extends Command
{
    // Exit codes: 0 feasible, 1 invalid input, 2 bad --variant, 3 infeasible.
    public const EXIT_INFEASIBLE = 3;

    public function __construct(private readonly OptimizationService $service)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('variant', null, InputOption::VALUE_REQUIRED, 'cost or makespan')
            ->addArgument('employees', InputArgument::OPTIONAL, 'Path to employees JSON (default: data/employees.json)')
            ->addArgument('tasks', InputArgument::OPTIONAL, 'Path to tasks JSON (default: data/tasks.json)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $variant = Variant::tryFrom((string) $input->getOption('variant'));
        if (null === $variant) {
            $output->writeln('<error>--variant must be "cost" or "makespan"</error>');

            return Command::INVALID;
        }

        try {
            $employeesFile = $input->getArgument('employees');
            $tasksFile = $input->getArgument('tasks');
            $result = $this->service->run(
                $variant,
                null === $employeesFile ? null : OptimizationService::readJsonFile($employeesFile),
                null === $tasksFile ? null : OptimizationService::readJsonFile($tasksFile),
            );
        } catch (InvalidInputException $e) {
            foreach ($e->errors as $error) {
                $output->writeln("<error>$error</error>");
            }

            return Command::FAILURE;
        }

        $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result->isFeasible() ? Command::SUCCESS : self::EXIT_INFEASIBLE;
    }
}
