<?php

namespace isamarin\Alisa;

/**
 * Class Alisa
 * @package Alisa
 */
class Alisa
{
    /** @var TriggerIterator $triggers */
    protected $triggers = [];
    /** @var Trigger $recornizedCommand */
    protected $recognizedCommand;
    /** @var SessionStorage $storage */
    protected $storage;
    /** @var Trigger $defaultCommand */
    protected $defaultCommand;
    /** @var Trigger $helloCommand */
    protected $helloCommand;
    /** @var Trigger $mistakeTrigger */
    protected $mistakeTrigger;

    protected $request;

    protected $skillName;

    protected $recognizedType;

    protected $directionType;


    /**
     * Alisa constructor.
     * @param string $skillName
     * @param null $data
     */
    public function __construct(string $skillName, $data = null)
    {
        $this->request = new Request($data);
        $this->skillName = $skillName;
        $this->triggers = new TriggerIterator();
        $this->storage = new SessionStorage($this->request);
        $this->recognizedType = RecognizedType::MORPHY_STRICT;
        $this->directionType = DirectionType::BACKWARD;

    }

    /**
     * @param int $RecognizedType
     * @see RecognizedType
     */
    public function setRecognizedAlgorithm(int $RecognizedType): void
    {
        if (in_array($RecognizedType, RecognizedType::getConstants(), true)) {
            $this->recognizedType = $RecognizedType;
        }
    }


    /**
     * Устанавливает триггер по-умолчанию
     * @param Trigger $trigger
     * @see Trigger::setAsDefault()
     * @deprecated
     */
    public function setDefaultTrigger(Trigger $trigger): void
    {
        if ($trigger->isValid()) {
            $this->defaultCommand = $trigger;
        } else {
            trigger_error('Некорректный триггер');
        }
    }

    /**
     * @param Trigger $trigger
     * @see Trigger::setAsInit()
     * @deprecated
     */
    public function setHelloTrigger(Trigger $trigger): void
    {
        if ($trigger->isValid()) {
            $this->helloCommand = $trigger;
        } else {
            trigger_error('Некорректный триггер');
        }
    }

    /**
     * @param Trigger $trigger
     * @see Trigger::setAsMistake()
     * @deprecated
     */
    public function setMistakeTrigger(Trigger $trigger): void
    {
        if ($trigger->isValid()) {
            $this->mistakeTrigger = $trigger;
        } else {
            trigger_error('Некорректный триггер');
        }
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return bool
     * TODO
     * reduce returns
     */
    protected function getCommand(): bool
    {
        /* Первое сообщение – приветствие */
        if ($this->request->getMessageID() === 0) {
            $this->recognizedCommand = $this->helloCommand;
            $this->storage->storeTrigger($this->recognizedCommand);
            return true;
        }
        /* Проверяем клик ли это на кнопку */
        if ($this->request->isButtonClick()) {
            $this->recognizedCommand = $this->triggers
                ->getByName($this->request->getPayloadData()['NAME']);
            return true;
        }

        /* Мб нам несут данные? */
        /**
         * TODO
         * здесь что то пошло не так, и используется какая то странная логика.
         * временный хотфикс
         * проблема связана с новым хранилищем сессий
         */
        if ($prev = $this->storage->getPreviousTrigger()->getName()) {
            $_previousCommand = $this->triggers->getByName($prev);
            if ($_previousCommand->isStoreData() && $_previousCommand->hasNextTrigger()) {
                $this->recognizedCommand = $_previousCommand->getNextTrigger();
                $this->storage->setItem($this->request->getUtterance(), $this->recognizedCommand->getName());
                $this->storage->storeTrigger($this->recognizedCommand);
                return true;
            }
            /* мб далее дефолт? */
            if ($_previousCommand->hasNextTrigger()
                && $_previousCommand->getNextTrigger() === $this->defaultCommand) {
                $this->recognizedCommand = $this->defaultCommand;
                $this->storage->storeTrigger($this->recognizedCommand);
                return true;
            }
        }

        if ($this->recognizeCommand()) {
            $this->storage->storeTrigger($this->recognizedCommand);
            return true;
        }


        /* Не удалось распознать */
        if ( ! $this->recognizedCommand) {
            $this->recognizedCommand = $this->mistakeTrigger;
            $this->storage->storeTrigger($this->recognizedCommand);
            return true;
        }
        return false;

    }


    protected function recognizeCommand(): bool
    {
        /**
         * @var DistanceRecognition|MorphyRecognition $recognizer
         */
        $recognizer = null;
        if ($this->recognizedType === RecognizedType::MORPHY_STRICT) {
            $recognizer = new MorphyRecognition();
        } else {
            $recognizer = new DistanceRecognition();
        }
        $results = [];
        /**
         * @var Trigger $trigger
         */
        foreach ($this->triggers as $trigger) {
            $results[$trigger->getName()] = $recognizer->rateSimilarities($this->request, $trigger);
            if ($this->recognizedType === RecognizedType::MORPHY_STRICT && $results[$trigger->getName()] === 1) {
                $this->recognizedCommand = $trigger;
                return true;
            }
        }
        if ($this->recognizedType === RecognizedType::DAMERAU_LEVENSHTEIN_DISTANCE) {
            sort($results);
            $this->recognizedCommand = $this->triggers->getByName(key($results));
            return true;
        }
        $this->recognizedCommand = $this->mistakeTrigger;

        return false;
    }


    /**
     * @param string $triggerName
     * @return mixed
     */
    public function getTriggerData(string $triggerName)
    {
        return $this->storage->getItem($triggerName);
    }

    /**
     * @return Trigger
     */
    public function getRecognizedCommand(): Trigger
    {
        return $this->recognizedCommand;
    }

    /**
     * @param Trigger ...$trigger
     * TODO
     * исправить работу итератора при пропуске дефолтных триггеров
     */
    public function addTrigger(Trigger ... $trigger): void
    {
        foreach ($trigger as $tr) {
            if ($tr->isValid()) {
                if ($tr->isMistake()) {
                    $this->mistakeTrigger = $tr;
                    //   continue;
                }
                if ($tr->isDefault()) {
                    $this->defaultCommand = $tr;
                    // continue;
                }
                if ($tr->isInit()) {
                    $this->helloCommand = $tr;
                    //   continue;
                }
                if ($this->defaultCommand && ! $tr->hasNextTrigger() && $this->defaultCommand->getName() !== $tr->getName()) {
                    $tr->setNextTrigger($this->defaultCommand);
                }
                $this->triggers->append($tr);
            }
        }

        $this->init();
    }

    protected function init(): void
    {
        if ( ! isset($this->helloCommand, $this->defaultCommand, $this->mistakeTrigger)) {
            $this->sendHelp();
            die();
        }
        if ( ! $this->recognizedCommand) {
            $this->getCommand();
        }

        if ( ! $this->request->isNewSession()) {
            if ($this->directionType === DirectionType::FORWARD) {
                $this->storage->setItem($this->recognizedCommand->getName(), $this->request->getUtterance());
            } else {
                $this->storage->setItem($this->storage->getPreviousTrigger()->getName(),
                    $this->request->getUtterance());
            }
            $this->storage->save();
        }
    }

    /**
     *
     * @param $direction
     */
    public function setTriggerDataDirection($direction): void
    {
        if (in_array($direction, DirectionType::getConstants(), true)) {
            $this->directionType = $direction;
        }
    }

    public function sendResponse(Trigger $trigger, callable $func): void
    {

        if ($trigger === $this->recognizedCommand) {
            /** @var Response $answer */
            $answer = $func();
            $response = $this->request->getServiceData();
            $response['response'] = $answer->send();
            print json_encode($response);
            $this->storage->save();
        }
    }

    protected function sendHelp(): void
    {
        $answer = new Response();
        $answer->addText('Приветствую! Бот запущен, но не настроены стандратные триггеры.');

        $button = new Button('Что такое стандартные триггеры?');
        $button->addLink('https://github.com/isamarin/alisa/tree/master#стандартные-триггеры');
        $button->setHide(true);

        $button2 = new Button('Посмотреть пример реализации');
        $button2->addLink('');
        $button2->setHide(true);


        $answer->addButton($button, $button2);
        $response = $this->request->getServiceData();
        $response['response'] = $answer->send();
        print json_encode($response);
        die();
    }


    public function storeCommonData($data, $key): void
    {
        $this->storage->setItem($key, $data);

    }

    public function getCommonData($key)
    {
        return $this->storage->getItem($key);
    }
}