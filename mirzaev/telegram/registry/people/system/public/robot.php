<?php

use DI\Container;
use Zanzara\Zanzara;
use Zanzara\Context;
use Zanzara\Telegram\Type\Input\InputFile;
use Zanzara\Config;
use React\Promise\PromiseInterface;

require __DIR__ . '/../../../../../../vendor/autoload.php';

const KEY = require('../settings/key.php');
const STORAGE = require('../settings/storage.php');

$config = new Config();
$config->setParseMode(Config::PARSE_MODE_MARKDOWN);

$bot = new Zanzara(KEY, $config);

$pdo = new \PDO('mysql:host=localhost;port=3306;dbname=telegram-registry-people;charset=utf8', 'dolboeb', 'sosiska228');

function isAdmin(int $id): bool
{
	global $pdo;

	return ($pdo->query("SELECT `admin` FROM accounts WHERE id_telegram=$id")->fetch(PDO::FETCH_ASSOC)['admin'] ?? 0) === 1;
}

function isActive(int $id): bool
{
	global $pdo;

	return ($pdo->query("SELECT `status` FROM accounts WHERE id_telegram=$id")->fetch(PDO::FETCH_ASSOC)['status'] ?? 'inactive') === 'active';
}

function countEntries(): array
{
	global $pdo;

	$date = time();

	$year = date('Y-m-d H:i:s', $date - 31556952);
	$month = date('Y-m-d H:i:s', $date - 2678400);
	$week = date('Y-m-d H:i:s', $date - 604800);
	$day = date('Y-m-d H:i:s', $date - 86400);

	return $pdo->query(
		<<<SQL
			SELECT 
				(SELECT COUNT(`id`) FROM `people`) AS 'total',
				(SELECT COUNT(`id`) FROM `people` WHERE `created` >= '$year') AS 'year',
				(SELECT COUNT(`id`) FROM `people` WHERE `created` >= '$month') AS 'month',
				(SELECT COUNT(`id`) FROM `people` WHERE `created` >= '$week') AS 'week',
				(SELECT COUNT(`id`) FROM `people` WHERE `created` >= '$day') AS 'day'
		SQL
	)->fetch(PDO::FETCH_ASSOC);
}

function lastUpdate(): string
{
	global $pdo;

	return date('Y\\\.m\\\.d H:i:s', strtotime($pdo->query('SELECT `updated` FROM people ORDER BY updated DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC)['updated'] ?? 0));
}

function initEntry(): ?int
{
	global $pdo;

	$pdo->query("INSERT INTO `people` () VALUES ()")->fetch();

	return $pdo->lastInsertId();
}

function generateMenu(Context $ctx): void
{
	$keyboard = [
		'reply_markup' =>
		['inline_keyboard' => [
			[
				['callback_data' => 'read', 'text' => '🔍 Поиск'],
			]
		], 'resize_keyboard' => false]
	];

	if (isAdmin($ctx->getMessage()?->getFrom()?->getId()) ?? $ctx->getCallbackQuery()->getFrom()->getId())
		$keyboard['reply_markup']['inline_keyboard'][0][] = ['callback_data' => 'write', 'text' => '📔 Записать'];

	$lastUpdate = lastUpdate();
	$count = countEntries();

	$ctx->sendMessage(
		<<<MARKDOWN
			🪄 *Главное меню*

			*Записано за сутки:* {$count['day']}
			*Записано за неделю:* {$count['week']}
			*Записано за месяц:* {$count['month']}
			*Записано за год:* {$count['year']}
			*Записано за всё время:* {$count['total']}

			*Последнее обновление:* $lastUpdate
		MARKDOWN,
		$keyboard
	);
}

function createEntry(
	Context $ctx,
	?string $name = null,
	?string $surname = null,
	?string $patronymic = null,
	?int $phone = null,
	?string $address = null,
	?int $year = null,
	?int $month = null,
	?int $day = null,
	?string $data = null,
	?string $cover = null
): void {
	$ctx->deleteUserDataItem('wait_for');

	match (null) {
		$name => waitFor($ctx, 'name'),
		$surname => waitFor($ctx, 'surname'),
		$patronymic => waitFor($ctx, 'patronymic'),
		$phone => waitFor($ctx, 'phone'),
		$address =>  waitFor($ctx, 'address'),
		$year => waitFor($ctx, 'year'),
		$month => waitFor($ctx, 'month'),
		$day =>  waitFor($ctx, 'day'),
		$data => waitFor($ctx, 'data'),
		$cover => waitFor($ctx, 'cover'),
		default => (function () use ($ctx) {
			$ctx->sendMessage('📦 *Все поля заполнены и записаны в реестре*')->then(function () use ($ctx) {
				stopProcess($ctx)->then(function () use ($ctx) {
					generateMenu($ctx);
				});
			});
		})()
	};
}

function readEntry(
	Context $ctx,
	?string $name = null,
	?string $surname = null,
	?string $patronymic = null,
	?int $phone = null,
	?string $address = null,
	?int $year = null,
	?int $month = null,
	?int $day = null,
	?string $data = null
): PromiseInterface {
	$ctx->deleteUserDataItem('wait_for');

	return match (null) {
		$name => waitFor($ctx, 'name'),
		$surname => waitFor($ctx, 'surname'),
		$patronymic => waitFor($ctx, 'patronymic'),
		$phone => waitFor($ctx, 'phone'),
		$address =>  waitFor($ctx, 'address'),
		$year => waitFor($ctx, 'year'),
		$month => waitFor($ctx, 'month'),
		$day =>  waitFor($ctx, 'day'),
		$data => waitFor($ctx, 'data'),
		default => (function () use ($ctx) {
			return $ctx->sendMessage('📦 *Все поля заполнены*');
		})()
	};
}

function generateQueryStatus(
	Context $ctx,
	?string $name = null,
	?string $surname = null,
	?string $patronymic = null,
	?int $phone = null,
	?string $address = null,
	?int $year = null,
	?int $month = null,
	?int $day = null,
	?string $data = null
): PromiseInterface {
	if (isset($name)) $name = preg_replace('/([._\-()!])/', '\\\$1', $name);
	if (isset($surname)) $surname = preg_replace('/([._\-()!])/', '\\\$1', $surname);
	if (isset($patronymic)) $patronymic = preg_replace('/([._\-()!])/', '\\\$1', $patronymic);
	if (isset($phone)) $phone = preg_replace('/([._\-()!])/', '\\\$1', $phone);
	if (isset($address)) $address = preg_replace('/([._\-()!])/', '\\\$1', $address);
	if (isset($year)) $year = preg_replace('/([._\-()!])/', '\\\$1', $year);
	if (isset($month)) $month =  preg_replace('/([._\-()!])/', '\\\$1', $month);
	if (isset($day)) $day = preg_replace('/([._\-()!])/', '\\\$1', $day);
	if (isset($data)) $data = preg_replace('/([._\-()!])/', '\\\$1', $data);

	$keyboard = generateFieldsButtons(
		...[
			'name' => true,
			'surname' => true,
			'patronymic' => true,
			'name' => true,
			'phone' => true,
			'address' => true,
			'year' => true,
			'month' => true,
			'day' => true,
			'data' => true
		]
	);

	$keyboard['reply_markup']['inline_keyboard'][] = [
		['callback_data' => 'stop', 'text' => '❎ Отмена'],
		['callback_data' => 'complete', 'text' => '✅ Отправить']
	];

	return $ctx->sendMessage(
		<<<MARKDOWN
			📝 *Настройка запроса*

			*Имя:* $name
			*Фамилия:* $surname
			*Отчество:* $patronymic
			*Номер:* $phone
			*Адрес:* $address
			*Дата рождения:* $year $month $day
			*Дополнительно:* $data
		MARKDOWN,
		$keyboard
	);
}


function generateRequestLabel(string $target, string|int|null $value = null): string
{
	$buffer = match ($target) {
		'name' => 'Введите имя',
		'surname' => 'Введите фамилию',
		'patronymic' => 'Введите отчество',
		'phone' => 'Введите номер телефона',
		'address' => 'Введите адрес',
		'year' => 'Введите год рождения',
		'month' => 'Введите номер месяца рождения',
		'day' => 'Введите номер дня рождения',
		'data' => 'Введите дополнительную информацию',
		'cover' => 'Отправьте обложку \(изображение\)',
		default => 'Введите данные для записи в реестр',
	};

	if (isset($value)) $buffer .= "\n\n*Текущее значение:* " . preg_replace('/([._\-()!])/', '\\\$1', $value);

	return $buffer;
}

function generateLabel(string $target): string
{
	return match ($target) {
		'name' => 'Имя',
		'surname' => 'Фамилия',
		'patronymic' => 'Отчество',
		'phone' => 'Номер',
		'address' => 'Адрес',
		'year' => 'Год',
		'month' => 'Месяц',
		'day' => 'День',
		'data' => 'Дополнительно',
		'cover' => 'Обложка',
		default => 'Информация',
	};
}

function generateFieldsButtons(
	?string $name = null,
	?string $surname = null,
	?string $patronymic = null,
	?int $phone = null,
	?string $address = null,
	?int $year = null,
	?int $month = null,
	?int $day = null,
	?string $data = null,
	?string $cover = null
): array {
	$buffer = [];
	$buffer2 = [];

	if (isset($name)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'name', 'text' => generateLabel('name')]
		: $buffer2[] = ['callback_data' => 'name', 'text' => generateLabel('name')];
	if (isset($surname)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'surname', 'text' => generateLabel('surname')]
		: $buffer2[] = ['callback_data' => 'surname', 'text' => generateLabel('surname')];
	if (isset($patronymic)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'patronymic', 'text' => generateLabel('patronymic')]
		: $buffer2[] = ['callback_data' => 'patronymic', 'text' => generateLabel('patronymic')];
	if (isset($phone)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'phone', 'text' => generateLabel('phone')]
		: $buffer2[] = ['callback_data' => 'phone', 'text' => generateLabel('phone')];
	if (isset($address)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'address', 'text' => generateLabel('address')]
		: $buffer2[] = ['callback_data' => 'address', 'text' => generateLabel('address')];
	if (isset($year)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'year', 'text' => generateLabel('year')]
		: $buffer2[] = ['callback_data' => 'year', 'text' => generateLabel('year')];
	if (isset($month)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'month', 'text' => generateLabel('month')]
		: $buffer2[] = ['callback_data' => 'month', 'text' => generateLabel('month')];
	if (isset($day)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'day', 'text' => generateLabel('day')]
		: $buffer2[] = ['callback_data' => 'day', 'text' => generateLabel('day')];
	if (isset($data)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'data', 'text' => generateLabel('data')]
		: $buffer2[] = ['callback_data' => 'data', 'text' => generateLabel('data')];
	if (isset($cover)) count($buffer) < 4
		? $buffer[] = ['callback_data' => 'cover', 'text' => generateLabel('cover')]
		: $buffer2[] = ['callback_data' => 'cover', 'text' => generateLabel('cover')];

	return ['reply_markup' => ['inline_keyboard' => [$buffer, $buffer2], 'resize_keyboard' => false]];
}

function waitFor(Context $ctx, string $target): PromiseInterface
{
	return $ctx->getUserDataItem('process')->then(function ($process) use ($ctx, $target) {
		if (isset($process))
			return $ctx->setUserDataItem("wait_for", $target)->then(function () use ($ctx, $target, $process) {
				return $ctx->sendMessage('⚠️ ' . generateRequestLabel($target, $process['data'][$target]), ['reply_markup' => ['inline_keyboard' => [[['callback_data' => 'delete_field', 'text' => 'Удалить']]], 'resize_keyboard' => false]]);
			});
	});
}

function updateEntry(int $id, string $name, string|int $value): void
{
	global $pdo;

	try {
		$pdo->prepare("UPDATE `people` SET `$name` = :value WHERE `id` = :id")->execute([':value' => $value, ':id' => $id]);
	} catch (Exception $e) {
	}
}

function checkEntry(int $id, string $name, string|int $value): bool
{
	global $pdo;

	$query = $pdo->prepare("SELECT `$name` FROM people WHERE `id` = :id");

	$query->execute([':id' => $id]);

	return $query->fetch(PDO::FETCH_ASSOC)[$name] === $value;
}

function startSearch(Context $ctx, string $order = 'updated', bool $desc = true, int $page = 1): PromiseInterface
{
	return $ctx->getUserDataItem('process')->then(function ($process) use ($ctx, $order, $desc, $page) {
		if (empty($process)) return;

		return stopProcess($ctx)->then(function () use ($ctx, $process, $order, $desc, $page) {
			return $ctx->sendMessage('⚙️ Запрос отправляется\.\.\.')->then(function () use ($ctx, $process, $order, $desc, $page) {
				foreach ($process['search']($order, $desc, 3, --$page, ...$process['data']) as $entry) {
					if (isset($entry['name'])) $entry['name'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['name']);
					if (isset($entry['surname'])) $entry['surname'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['surname']);
					if (isset($entry['patronymic'])) $entry['patronymic'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['patronymic']);
					if (isset($entry['phone'])) $entry['phone'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['phone']);
					if (isset($entry['address'])) $entry['address'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['address']);
					if (isset($entry['year'])) $entry['year'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['year']);
					if (isset($entry['month'])) $entry['month'] =  preg_replace('/([._\-()!])/', '\\\$1', $entry['month']);
					if (isset($entry['day'])) $entry['day'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['day']);
					if (isset($entry['data'])) $entry['data'] = preg_replace('/([._\-()!])/', '\\\$1', $entry['data']);

					$text = "*Имя:* {$entry['name']}\n*Фамилия:* {$entry['surname']}\n*Отчество:* {$entry['patronymic']}\n*Номер:* {$entry['phone']}\n*Адрес:* {$entry['address']}\n*Дата рождения:* {$entry['year']} {$entry['month']} {$entry['day']}\n*Дополнительно:* {$entry['data']}";

					$file = parse_url($entry['cover'])['path'];

					if (file_exists($file)) $ctx->sendPhoto(new InputFile($file), ['caption' => $text, 'protect_content' => true]);
					else $ctx->sendMessage($text, ['protect_content' => true]);
				}
			});
		});
	});
}

function searchSmartEntry(
	string $order = 'updated',
	bool $desc = true,
	int $limit = 3,
	int $page = 0,
	?string $name = null,
	?string $surname = null,
	?string $patronymic = null,
	?int $phone = null,
	?string $address = null,
	?int $year = null,
	?int $month = null,
	?int $day = null,
	?string $data = null
): array {
	global $pdo;

	if (
		empty($name)
		&& empty($surname)
		&& empty($patronymic)
		&& empty($phone)
		&& empty($address)
		&& empty($year)
		&& empty($month)
		&& empty($day)
		&& empty($data)
	) return [];

	$query = 'SELECT * FROM `people` WHERE ';
	$args = [];
	$another = false;

	if (isset($name)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`name`, :name) < 3 && `name` != \'\'';
		$args[':name'] = $name;
	}

	if (isset($surname)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`surname`, :surname) < 3 && `surname` != \'\'';
		$args[':surname'] = $surname;
	}

	if (isset($patronymic)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`patronymic`, :patronymic) < 3 && `patronymic` != \'\'';
		$args[':patronymic'] = $patronymic;
	}

	if (isset($phone)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`phone`, :phone) < 2 && `phone` != \'\'';
		$args[':phone'] = $phone;
	}

	if (isset($address)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`address`, :address) < 4 && `address` != \'\'';
		$args[':address'] = $address;
	}

	if (isset($year)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= '`year` == :year';
		$args[':year'] = $year;
	}

	if (isset($month)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= '`month` == :month';
		$args[':month'] = $month;
	}

	if (isset($day)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= '`day` == :day';
		$args[':day'] = $day;
	}

	if (isset($data)) {
		if ($another) $query .= ' && ';
		else $another = true;
		$query .= 'levenshtein(`data`, :data) < 6 && `data` != \'\'';
		$args[':data'] = $data;
	}

	$query .= " ORDER BY `$order` " . ($desc ? 'DESC' : 'ASC');

	$offset = $page === 0 ? 0 : $limit * $page;
	$query .= " LIMIT $limit OFFSET $offset";

	try {
		$instance = $pdo->prepare($query);
		if ($instance->execute($args)) return $instance->fetchAll(PDO::FETCH_ASSOC);
		else return [];
	} catch (Exception $e) {
	}

	return [];
}

$stop = false;

$bot->onUpdate(function (Context $ctx) use (&$stop): void {
	if (!isActive($ctx->getMessage()?->getFrom()?->getId() ?? $ctx->getCallbackQuery()->getFrom()->getId())) $stop = true;
});

$bot->onCommand('start', function (Context $ctx) use ($stop): void {
	if ($stop) return;
	generateMenu($ctx);
});

$bot->onMessage(function (Context $ctx) use ($stop): void {
	$text = $ctx->getMessage()->getText();

	if (!empty($text) && $text[0] !== '/' || empty($text))
		$ctx->getUserDataItem('process')->then(function ($process) use ($ctx, $text) {
			if (empty($process)) return;

			$ctx->getUserDataItem('wait_for')->then(function ($wait_for) use ($ctx, &$process, $text) {
				$target =	match ($wait_for) {
					'phone', 'day', 'month', 'year' => (function () use ($ctx, $text) {
						preg_match_all('!\d+!', $text, $matches);
						return (int) implode('', $matches[0]);
					})(),
					default => $text
				};

				if ($process['type'] === 'createEntry') {
					// Создание записи в реестре

					if ($wait_for === 'cover') {
						if (!file_exists($path = 'storage/' . $process['id'])) mkdir($path, '0755', true);

						$photos = $ctx->getMessage()->getPhoto();

						$ctx->getFile(end($photos)->getFileId())->then(function ($file) use ($ctx, $wait_for, &$path, &$process, &$target) {
							$url = pathinfo($file->getFilePath());

							if (!file_exists($path .= '/' . $url['dirname'])) mkdir($path, '0755', true);

							file_put_contents($path .= '/' . $url['basename'], fopen('https://api.telegram.org/file/bot' . KEY . '/' . $file->getFilePath(), 'r'));
							updateEntry($process['id'], $wait_for, $path);

							if (checkEntry($process['id'], $wait_for, $path)) {
								$process['data'][$wait_for] = $path;
								$ctx->setUserDataItem('process', $process)->then(function () use ($ctx, $path, $process) {
									$ctx->sendMessage("✏️ *Записано в реестр*\n\n" . generateLabel('cover') . ': ' . ($link = preg_replace('/([._\-()!])/', '\\\$1', STORAGE . '/' . $path)) . "\n[]($link)")->then(function () use ($ctx, $process) {
										// Запуск процесса создания
										createEntry($ctx, ...$process['data']);
									});
								});
							} else $ctx->sendMessage('🚫 Не удалось записать значение в реестр');
						});
					} else {
						updateEntry($process['id'], $wait_for, $target);

						if (checkEntry($process['id'], $wait_for, $target)) {
							$process['data'][$wait_for] = $target;
							$ctx->setUserDataItem('process', $process)->then(function () use ($ctx, $target, $wait_for, $process) {
								$ctx->sendMessage("✏️ *Записано в реестр*\n\n" . generateLabel($wait_for) . ': ' . preg_replace('/([._\-()!])/', '\\\$1', $target))->then(function () use ($ctx, $process) {
									// Запуск процесса создания
									createEntry($ctx, ...$process['data']);
								});
							});
						} else $ctx->sendMessage('🚫 *Не удалось записать значение в реестр*');
					}
				} else if ($process['type'] === 'readEntry') {
					// Чтение записей в реестре

					$process['data'][$wait_for] = $target;
					$ctx->setUserDataItem('process', $process)->then(function () use ($ctx, $process) {
						generateQueryStatus($ctx, ...$process['data'])->then(function () use ($ctx, $process) {
							readEntry($ctx, ...$process['data']);
						});
					});
				}
			});
		});
});

function read(Context $ctx, bool $smart = false): void
{
	global $stop;

	if ($stop) return;

	// Инициализация процесса в кеше
	$ctx->setUserDataItem('process', [
		'type' => 'readEntry',
		'search' => 'searchSmartEntry',
		'data' => $data = [
			'name' => null,
			'surname' => null,
			'patronymic' => null,
			'phone' => null,
			'address' => null,
			'year' => null,
			'month' => null,
			'day' => null,
			'data' => null
		]
	])->then(function () use ($ctx, $data) {
		$ctx->sendMessage("⚡ *Запущен процесс поиска*")->then(function () use ($ctx, $data) {
			generateQueryStatus($ctx, ...$data)->then(function () use ($ctx, $data) {
				// Запуск процесса создания поиска
				readEntry($ctx, ...$data);
			});
		});
	});
}

function write(Context $ctx): void
{
	global $stop;

	if ($stop) return;

	if (isAdmin($ctx->getMessage()?->getFrom()?->getId() ?? $ctx->getCallbackQuery()->getFrom()->getId())) {
		// Администратор

		if ($id = initEntry()) {
			// Инициализирован человек в базе данных

			// Инициализация процесса в кеше
			$ctx->setUserDataItem('process', [
				'type' => 'createEntry',
				'id' => $id,
				'data' => $data = [
					'name' => null,
					'surname' => null,
					'patronymic' => null,
					'phone' => null,
					'address' => null,
					'year' => null,
					'month' => null,
					'day' => null,
					'data' => null,
					'cover' => null
				]
			])->then(function () use ($ctx, $id, $data) {
				$ctx->sendMessage("⚡ *Запущен процесс создания записи*")->then(function () use ($ctx, $data, $id) {
					$ctx->sendMessage("📦 *Инициализирована запись в реестре:* $id")->then(function () use ($ctx, $data, $id) {
						// Запуск процесса создания
						createEntry($ctx, ...$data);
					});
				});
			});
		}
	}
}

function stopProcess(Context $ctx): PromiseInterface
{
	return $ctx->deleteUserDataItem('process')->then(function () use ($ctx) {
		return $ctx->deleteUserDataItem('wait_for')->then(function () use ($ctx) {
			return $ctx->sendMessage('⛔ Процесс завершён');
		});
	});
}

function deleteField(Context $ctx): void
{
	$ctx->getUserDataItem('process')->then(function ($process) use ($ctx) {
		$ctx->getUserDataItem('wait_for')->then(function ($wait_for) use ($ctx, $process) {
			$process['data'][$wait_for] = null;
			$ctx->setUserDataItem('process', $process)->then(function () use ($ctx, $process, $wait_for) {
				$ctx->sendMessage('🗑️ *Удалено значение поля:* ' . mb_strtolower(generateLabel($wait_for)))->then(function () use ($ctx, $process) {
					generateQueryStatus($ctx, ...$process['data'])->then(function () use ($ctx, $process) {
						$process['type']($ctx, ...$process['data']);
					});
				});
			});
		});
	});
}

$bot->onCommand('write', fn ($ctx) => write($ctx));
$bot->onCommand('read', fn ($ctx) => read($ctx));
$bot->onCommand('read_smart', fn ($ctx) => read($ctx, true));

$bot->onCbQueryData(['write'], fn ($ctx) => write($ctx));
$bot->onCbQueryData(['read'], fn ($ctx) => read($ctx));
$bot->onCbQueryData(['read_smart'], fn ($ctx) => read($ctx, true));

$bot->onCommand('name', fn ($ctx) => waitFor($ctx, 'name'));
$bot->onCommand('surname', fn ($ctx) => waitFor($ctx, 'surname'));
$bot->onCommand('patronymic', fn ($ctx) => waitFor($ctx, 'patronymic'));
$bot->onCommand('phone', fn ($ctx) => waitFor($ctx, 'phone'));
$bot->onCommand('address', fn ($ctx) => waitFor($ctx, 'address'));
$bot->onCommand('year', fn ($ctx) => waitFor($ctx, 'year'));
$bot->onCommand('month', fn ($ctx) => waitFor($ctx, 'month'));
$bot->onCommand('day', fn ($ctx) => waitFor($ctx, 'day'));
$bot->onCommand('data', fn ($ctx) => waitFor($ctx, 'data'));
$bot->onCommand('cover', fn ($ctx) => waitFor($ctx, 'cover'));

$bot->onCbQueryData(['name'], fn ($ctx) => waitFor($ctx, 'name'));
$bot->onCbQueryData(['surname'], fn ($ctx) => waitFor($ctx, 'surname'));
$bot->onCbQueryData(['patronymic'], fn ($ctx) => waitFor($ctx, 'patronymic'));
$bot->onCbQueryData(['phone'], fn ($ctx) => waitFor($ctx, 'phone'));
$bot->onCbQueryData(['address'], fn ($ctx) => waitFor($ctx, 'address'));
$bot->onCbQueryData(['year'], fn ($ctx) => waitFor($ctx, 'year'));
$bot->onCbQueryData(['month'], fn ($ctx) => waitFor($ctx, 'month'));
$bot->onCbQueryData(['day'], fn ($ctx) => waitFor($ctx, 'day'));
$bot->onCbQueryData(['data'], fn ($ctx) => waitFor($ctx, 'data'));
$bot->onCbQueryData(['cover'], fn ($ctx) => waitFor($ctx, 'cover'));

$bot->onCbQueryData(['delete_field'], fn ($ctx) => deleteField($ctx));

$bot->onCommand('stop', fn ($ctx) => stopProcess($ctx));
$bot->onCbQueryData(['stop'], fn ($ctx) => stopProcess($ctx));

$bot->onCommand('complete', fn ($ctx) => startSearch($ctx));
$bot->onCbQueryData(['complete'], fn ($ctx) => startSearch($ctx));

$bot->run();
