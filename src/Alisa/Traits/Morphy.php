<?php

namespace isamarin\Alisa\Traits;

use phpMorphy;
use phpMorphy_FilesBundle;

/**
 * Trait MorphyRecognition
 *
 * Добавляет возможность работы с морфологическим преобразованем слов
 * @package Alisa
 */
Trait Morphy
{
    /**
     * Преобразует слова массива слов в начальную форму
     * @param array $words
     * @return array
     * @see Request::getWords()
     */
    public function convertToBaseForm(array $words): array
    {
        $morphy = new phpMorphy(new phpMorphy_FilesBundle($GLOBALS['DICTS_PATH'] ?? $_SERVER['DOCUMENT_ROOT'] . '/dicts/',
            'rus'), [
            'storage' => PHPMORPHY_STORAGE_FILE,
            'with_gramtab' => false,
            'predict_by_suffix' => true,
            'predict_by_db' => true,
        ]);
        $out = [];
        foreach ($words as &$word) {
            $word = mb_strtoupper($word);
            $res = $morphy->getBaseForm($word)[0];
            if ($res) {
                $out[] = $res;
            } else {
                $out[] = $word;
            }
        }
        return $out;
    }
}