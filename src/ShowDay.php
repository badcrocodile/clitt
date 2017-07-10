<?php namespace Acme;


use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Carbon\Carbon;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class ShowDay extends ShowDates {

    public function configure()
    {
        $this->setName('day')
            ->setDescription('Display times logged during a specific day.')
            ->addArgument('day', InputArgument::OPTIONAL);
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int|null|void
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $day                = $input->getArgument('day');
        $date_day          = (isset($day) ? new Carbon($day) : new Carbon());
        $date_day_start    = (new Carbon($date_day))->startOfDay()->timestamp;
        $date_day_end      = (new Carbon($date_day))->endOfDay()->timestamp;

        $sessions = $this->database->selectWhere("
            SELECT entries.id, project_id, start_time, stop_time, name
            FROM entries
            JOIN projects
            ON entries.project_id = projects.id
            WHERE stop_time 
            BETWEEN $date_day_start AND $date_day_end
        ");

        $comments = $this->database->selectWhere("
            SELECT comments.comment, entries.id, projects.name
            FROM comments
            LEFT JOIN entries
            ON entries.id = comments.entry_id
            LEFT JOIN projects
            ON entries.project_id = projects.id 
            WHERE stop_time 
            BETWEEN $date_day_start AND $date_day_end
        ");


        $session = new Session($sessions);

        $sessions_table = new Table($output);

        $comments_table = new Table($output);

        $project_total = $session->formatProjectTotal();

        $table_header_message = (new OutputMessage((new Carbon($date_day))->startOfDay()->toFormattedDateString()))->asComment();

        $table_headers[] = [new TableCell($table_header_message, ['colspan' => 6])];
        $table_headers[] = ['ID', 'Project', 'Date', 'Start Time', 'Stop Time', 'Session Length'];

        $table_rows   = $session->getSessionTimesWithProjectName();
        $table_rows[] = new TableSeparator();
        $table_rows[] = [new TableCell("<comment>Total:</comment>", array('colspan' => 5)), new TableCell("<comment>$project_total</comment>", ['colspan' => 1])];

        $sessions_table->setHeaders($table_headers)->setRows($table_rows)->render();

        $comments_table_header_message = (new OutputMessage("Comments on " . (new Carbon($date_day))->toFormattedDateString()))->asComment();

        $x = 0;
        $comment_array = [];
        foreach($comments as $comment) {
            $comment_array[$x]['id'] = $comment['id'];
            $comment_array[$x]['project'] = $comment['name'];
            $comment_array[$x]['comment'] = $comment['comment'];
            $x++;
        }

        $comments_table_headers[] = [new TableCell($comments_table_header_message, ['colspan' => 3])];
        $comments_table_headers[] = ['ID', 'Project', 'Comment'];
        $comments_table_rows = $comments_table;

        $comments_table->setHeaders($comments_table_headers)->setRows($comment_array)->render();

        $this->paginate($input, $output, $date_day);
    }

    /**
     * Paginates through results by week
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param                                                   $starting_day
     *
     * @internal param $starting_week
     */
    public function paginate(InputInterface $input, OutputInterface $output, $starting_day) {
        $current_day = $starting_day;

        $helper = $this->getHelper('question');

        $output->writeln((new OutputMessage("")));

        $question = new Question('([<info>n</info>]ext | [<info>p</info>]revious | [<info>e</info>]xport | [<info>q</info>]uit) => ', 'null');

        $paginate = $helper->ask($input, $output, $question);

        switch ($paginate) {
            case "n":
                $input->setArgument('day', $current_day->addDay());
                $this->execute($input, $output);
                break;
            case "p":
                $input->setArgument('day', $current_day->subDay());
                $this->execute($input, $output);
                break;
            case "a":
                return;
        }
    }
}