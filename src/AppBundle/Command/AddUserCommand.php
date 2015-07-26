<?php

namespace AppBundle\Command;

use AppBundle\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AddUserCommand extends ContainerAwareCommand {
    const MAX_ATTEMPTS = 5;

    /**
     * @var ObjectManager
     */
    private $em;

    protected function configure() {
        $this
            ->setName('app:user-add')
            ->setDescription('Creates users and stores them in the database')
            ->setHelp($this->getCommandHelp())
            ->addArgument('username', InputArgument::OPTIONAL, 'The username of the new user')
            ->addArgument('password', InputArgument::OPTIONAL, 'The plain password of the new user')
            ->addArgument('email', InputArgument::OPTIONAL, 'The email of the new user')
            ->addOption('is-admin', null, InputOption::VALUE_NONE, 'If set, the user is created as an administrator');
    }

    protected function initialize(InputInterface $input, OutputInterface $output) {
        $this->em = $this->getContainer()->get('doctrine')->getManager();
    }

    protected function interact(InputInterface $input, OutputInterface $output) {
        if(null !== $input->getArgument('username') && null !== $input->getArgument('password') && null !== $input->getArgument('email')) {
            return;
        }

        $output->writeln([
            '',
            'Add User Command Interactive Wizard',
            '-----------------------------------',
            '',
            'If you prefer to not use this interactive wizard, provide the arguments required by this command as follows:',
            '',
            '$ php app/console app:add-user username password email@example.com',
            ''
        ]);

        $console = $this->getHelper('question');

        $username = $input->getArgument('username');

        if(null === $username) {
            $question = new Question(' > <info>Username</info>: ');
            $question->setValidator(function($answer) {
                if(empty($answer)) {
                    throw new \RuntimeException('The username cannot be empty');
                }

                return $answer;
            });
            $question->setMaxAttempts(self::MAX_ATTEMPTS);

            $username = $console->ask($input, $output, $question);
            $input->setArgument('username', $username);
        } else {
            $output->writeln(' > <info>Username</info>: '.$username);
        }

        $password = $input->getArgument('password');

        if(null === $password) {
            $question = new Question(' > <info>Password</info> (your type will be hidden): ');
            $question->setValidator(array($this, 'passwordValidator'));
            $question->setHidden(true);
            $question->setMaxAttempts(self::MAX_ATTEMPTS);

            $password = $console->ask($input, $output, $question);
            $input->setArgument('password', $password);
        } else {
            $output->writeln(' > <info>Password</info>: '.str_repeat('*', strlen($password)));
        }

        $email = $input->getArgument('email');

        if(null === $email) {
            $question = new Question(' > <info>E-Mail</info>: ');
            $question->setValidator(array($this, 'emailValidator'));
            $question->setMaxAttempts(self::MAX_ATTEMPTS);

            $email = $console->ask($input, $output, $question);
            $input->setArgument('email', $email);
        } else {
            $output->writeln(' > <info>Email</info>: '.$email);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $startTime = microtime(true);

        $username = $input->getArgument('username');
        $plainPassword = $input->getArgument('password');
        $email = $input->getArgument('email');
        $isAdmin = $input->getOption('is-admin');

        $existingUser = $this->em->getRepository('AppBundle:User')->findOneBy(['username'  => $username ]);

        if(null !== $existingUser) {
            throw new \RuntimeException(sprintf('There is already a user registered with the "%s" username.', $username));
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles([ $isAdmin ? 'ROLE_ADMIN' : 'ROLE_USER' ]);

        $encoder = $this->getContainer()->get('security.password_encoder');
        $encodedPassword = $encoder->encodePassword($user, $plainPassword);
        $user->setPassword($encodedPassword);

        $this->em->persist($user);
        $this->em->flush($user);

        $output->writeln('');

        $output->writeln(sprintf('[OK] %s was successfully created: %s (%s)', $isAdmin ? 'Administrator user' : 'User', $user->getUsername(), $user->getEmail()));

        if ($output->isVerbose()) {
            $finishTime = microtime(true);
            $elapsedTime = $finishTime - $startTime;

            $output->writeln(sprintf('[INFO] New user database id: %d / Elapsed time: %.2f ms', $user->getId(), $elapsedTime*1000));
        }
    }

    /**
     * This internal method should be private, but it's declared as public to
     * maintain PHP 5.3 compatibility when using it in a callback.
     *
     * @internal
     */
    public function passwordValidator($plainPassword)
    {
        if (empty($plainPassword)) {
            throw new \Exception('The password can not be empty');
        }

        if (strlen(trim($plainPassword)) < 6) {
            throw new \Exception('The password must be at least 6 characters long');
        }

        return $plainPassword;
    }

    /**
     * This internal method should be private, but it's declared as public to
     * maintain PHP 5.3 compatibility when using it in a callback.
     *
     * @internal
     */
    public function emailValidator($email)
    {
        if (empty($email)) {
            throw new \Exception('The email can not be empty');
        }

        if (false === strpos($email, '@')) {
            throw new \Exception('The email should look like a real email');
        }

        return $email;
    }

    /**
     * The command help is usually included in the configure() method, but when
     * it's too long, it's better to define a separate method to maintain the
     * code readability.
     */
    private function getCommandHelp()
    {
        return <<<HELP
The <info>%command.name%</info> command creates new users and saves them in the database:

  <info>php %command.full_name%</info> <comment>username password email</comment>

By default the command creates regular users. To create administrator users,
add the <comment>--is-admin</comment> option:

  <info>php %command.full_name%</info> username password email <comment>--is-admin</comment>

If you omit any of the three required arguments, the command will ask you to
provide the missing values:

  # command will ask you for the email
  <info>php %command.full_name%</info> <comment>username password</comment>

  # command will ask you for the email and password
  <info>php %command.full_name%</info> <comment>username</comment>

  # command will ask you for all arguments
  <info>php %command.full_name%</info>

HELP;
    }
}