<?php

namespace isamarin\Alisa;

/**
 * Class Response
 * @package isamarin\Alisa
 */
class Response
{
    private $answers;
    private $buttons = [];
    private $paginatorLength;
    private $recognized;
    public const YANDEX_TEXT = 'text';
    public const YANDEX_TTS = 'tts';

    /**
     * Response constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param string $text
     * @param string|null $tts
     * @return $this
     */


    public function addText(string $text, string $tts = null): self
    {
        $answer = [];
        if ($text) {
            $answer[self::YANDEX_TEXT] = $text;
            if ($tts) {
                $answer[self::YANDEX_TTS] = $tts;
            } else {
                $answer[self::YANDEX_TTS] = $text;
            }
            $this->answers[] = $answer;
        }
        return $this;
    }

    /**
     * @param Button ...$buttons
     * @return $this
     */
    public function addButton(Button ... $buttons): self
    {
        $this->merge($buttons);
        return $this;
    }

    /**
     * @param $buttons
     */
    protected function merge($buttons): void
    {
        if ($buttons) {
            $this->buttons = array_merge($this->buttons, $buttons);
        }
    }

    /**
     * @param array $buttons
     * @return $this
     */
    public function addButtonsArray(array $buttons): self
    {
        $this->merge($buttons);
        return $this;
    }

    /**
     * @param int $length
     */
    public function setButtonsPaginator(int $length): void
    {
        $this->paginatorLength = $length;
    }

    /**
     * @return array
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    /**
     *
     */
    public function resetButtons(): void
    {
        $this->buttons = [];
    }

    /**
     * @param Button $button
     */

    public function deleteButton(Button $button): void
    {
        foreach ($this->buttons as $key => $currentButton) {
            /** @var Button $currentButton */
            if ((string)$button === (string)$currentButton) {
                unset($this->buttons[$key]);
            }
        }
    }

    /**
     * Не использовать!
     * @param $payload
     * @param $recognized
     * @param $keepPreviosData
     * @internal
     */
    public function serviceActions($payload, $recognized, $keepPreviosData): void
    {
        if ($this->paginatorLength) {
            $pag = new Paginator($payload, $recognized, $keepPreviosData);
            $pag->setLimit($this->paginatorLength);
            foreach ($this->buttons as $button) {
                $pag->append($button);
            }
            $this->buttons = $pag->getPaginated();
        }
    }

    /**
     * @param Trigger $recognized
     * @return array
     */
    public function send(Trigger $recognized): array
    {
        $rawButtons = [];
        $this->buttons = array_unique($this->buttons);
        foreach ($this->buttons as $button) {
            /** @var Button $button */
            $raw = $button->get();
            if (isset($raw[Button::PAYLOAD][Button::ATTACH]) && $raw[Button::PAYLOAD][Button::ATTACH] === true) {
                $raw[Button::PAYLOAD][Button::ATTACH] = $recognized->getName();
            }
            $rawButtons[] = $raw;
        }

        $text = $this->answers[array_rand($this->answers, 1)];
        if ($this->buttons) {
            $text['buttons'] = $rawButtons;
        }
        return $text;
    }

}