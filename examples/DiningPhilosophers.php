<?php

include dirname(dirname(__FILE__)).'/src/autoload.php';

class Philosopher
{
	protected $name;
	protected $color;
	protected $state = 'thinking';
	protected $left_fork;
	protected $right_fork;
	protected $process;

	public function __construct(phm\Process\Factory $process_factory, phm\Lock\Mutex $left_fork, phm\Lock\Mutex $right_fork, $name, $color)
	{
		$this->left_fork = $left_fork;
		$this->right_fork = $right_fork;
		$this->process_factory = $process_factory;
		$this->name = $name;
		$this->color = $color;
	}

	public function __destruct()
	{
		if ( ! $this->process->isCurrent())
		{
			$this->process->signal(SIGKILL);
		}
	}

	public function __get($var)
	{
		return $this->$var;
	}

	public function __isset($var)
	{
		return isset($this->$var);
	}

	public function __toString()
	{
		return (string) $this->name;
	}

	public function activate()
	{
		$this->process = $this->process_factory->fork();
		
		if ($this->process->isCurrent()) {
			while (true)
			{
				$this->think();
				$this->eat();
			}
		} else {
			return;
		}
	}

	public function think()
	{
		$this->state = 'thinking';
		$this->outLn("$this is thinking.");
		sleep(rand(1, 5));
	}

	public function eat()
	{
		$this->outLn("$this is hungry.");
		
		$this->left_fork->acquire();
		$this->outLn("$this has the left fork: 0x".dechex($this->left_fork->getKey()));
		$this->right_fork->acquire();
		$this->outLn("$this has the right fork: 0x".dechex($this->right_fork->getKey()));

		$this->state = 'eating';
		$this->outLn("$this is eating.");
		sleep(rand(1, 5));
		
		$this->right_fork->release();
		$this->left_fork->release();
        $this->outLn("$this is finished.");


	}

	public function outLn($str)
	{
		echo $this->color . $str . "\033[0m\n";
	}
}

$forks = array();
$philosophers = array();
$names = array(
	'Aristotle',
	'  Archimedes',
	'    Plato',
	'      Socrates',
	'        Pythagoras',
);
$colors = array(
	"\033[0;35m",
	"\033[0;31m",
	"\033[0;32m",
	"\033[0;33m",
	"\033[0;34m",
);
$process_factory = new phm\Process\Factory();
$phm_factory = phm\Factory::getInstance();

// создаем мутексы
for ($i = 0; $i < 5; $i++)
{
	$forks[$i] = $phm_factory->newMutex('Fork '.$i);
}

echo "\n";
echo "-------------------------------------------------------\n";
echo "The five philosophers are dining \n";
echo "-------------------------------------------------------\n";
echo "\n";


// создаем 5 процессов - философо
// каждый процес имеет два мутекс-симафоры которые ассоциируються с вилками
//   0/1, 1/2, 2/3, 3/4, 4/0
for ($i = 0, $j = 1; $i < 5; $i++, $j = ($i + 1) % 5)
{

    //  Вилка должны быть приобретены в порядке возрастания
// чтобы избежать тупиков. чтобы сделать это, назначив
// вилка с самым низким номером как «левая»
// (даже если это действительно правая вилка).
	if ($j > $i)
	{
		$philosophers[$i] = new Philosopher($process_factory, $forks[$i], $forks[$j], $names[$i], $colors[$i]);
	}
	else
	{
		$philosophers[$i] = new Philosopher($process_factory, $forks[$j], $forks[$i], $names[$i], $colors[$i]);
	}

	$philosophers[$i]->activate();

}

///очистка ресурсов
declare(ticks = 1);
$signal_handler = function($signal) use(&$philosophers, &$forks, $phm_factory)
{
	unset($philosophers);
	usleep(500000);
	foreach ($forks as $i => $mutex)
	{
		$key = $mutex->getKey();
		$mutex->delete();
		$phm_factory->getKeyring()->removeKey($key);
	}
	exit(0);
};
pcntl_signal(SIGINT, $signal_handler);
pcntl_signal(SIGTERM, $signal_handler);

while (true)
{
	sleep(rand(1, 60));
}

echo "\n\n";

