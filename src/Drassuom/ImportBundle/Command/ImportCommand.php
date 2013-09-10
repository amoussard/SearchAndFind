<?php
namespace Drassuom\ImportBundle\Command;

use Drassuom\ImportBundle\Entity\Import;
use Drassuom\ImportBundle\Manager\ImportManager;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\HttpFoundation\File\File;

use Drassuom\ImportBundle\Exception\ImportException;

class ImportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('drassuom:import')
            ->setDescription('Import file')
            ->addArgument('file', InputArgument::REQUIRED, 'The file to import')
            ->addArgument('type', InputArgument::REQUIRED, 'Import Type')
            ->addOption('start', null, InputOption::VALUE_OPTIONAL, 'Start')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Dry run')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Strict mode (stop on error)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $aOptions = $this->extractOptions($input, $output);

        if (!file_exists($aOptions["file"])) {
            throw new \Exception("file [".$aOptions["file"]."] does not exists");
        }

        if (is_dir($aOptions["file"])) {
            throw new \Exception("file [".$aOptions["file"]."] is a directory");
        }

        $oBeginAt = new \DateTime();
        $oFile = new File($aOptions["file"], "r");
        $oImport = new Import();
        $oImport->setFile($oFile);

        $output->writeln(sprintf('Start importing file: <comment>%s</comment> ... at <comment>%s</comment>', $oFile->getFilename(), $oBeginAt->format('c')));
        $oManager = $this->getContainer()->get("nova_import.manager");

        $aImportList = array($aOptions["type"] => $oImport);
        /** @var ImportManager $oManager */
        $oManager->import($aImportList, $aOptions);

        if ($oImport->getSuccess()) {
            $output->writeln('** <info>Success</info> **');
            $output->writeln('<info>Rows Inserted: '.$oImport->getRowsInserted().'</info>');
            $output->writeln('<info>Rows Updated: '.$oImport->getRowsUpdated().'</info>');
            $output->writeln('<comment>Rows Skipped: '.$oImport->getRowsSkipped().'</comment>');

        } else {
            $output->writeln('<error>!! Error !!</error> ');
        }

        $iNbErrors = $oImport->getNbErrors();
        if ($iNbErrors > 0) {
            $output->writeln('<error>Errors: '.$iNbErrors.'</error>');
        }

        $aErrorList = $oImport->getErrorList();
        foreach ($aErrorList as $oError) {
            if ($oError instanceof ImportException && $oError->isFatal()) {
                $output->writeln("\t<error>".$oError."</error>");
            } else {
                $output->writeln("\t<comment>".$oError."</comment>");
            }
        }

        $aWarningList = $oImport->getWarningList();
        $iNbWarn = count($aWarningList);
        if ($iNbWarn > 0) {
            $output->writeln('<comment>Warning: '.$iNbWarn.'</comment>');
        }
        foreach ($aWarningList as $oWarning) {
            $output->writeln("\t<comment>".$oWarning."</comment>");
        }

        $output->writeln('Execution time: <comment>'.$oImport->getExecutionTime().'</comment>');
        $oEndAt = new \DateTime();
        $output->writeln(sprintf('End at <comment>%s</comment>', $oEndAt->format('c')));
    }

    protected function extractOptions(InputInterface $input, OutputInterface $output) {
        $bVerbose = $input->getOption('verbose');
        $sFile = $input->getArgument('file');
        $sType = $input->getArgument('type');
        $iLimit = $input->getOption('limit');
        $iStart = $input->getOption('start');
        $bDryRun = $input->getOption('dry-run');
        $bStrict = $input->getOption('strict');
        $aFilters = array();

        $options = array(
            'file'          => $sFile,
            'type'          => $sType,
            'start'         => $iStart,
            'limit'         => $iLimit,
            'output'        => $output,
            'dry-run'       => $bDryRun,
            'verbose'       => $bVerbose,
            'filters'       => $aFilters,
        );
        if ($bStrict == 0) {
            $options['stopOnError'] = false;
            $options['rollback'] = false;
        }

        return $options;
    }
}