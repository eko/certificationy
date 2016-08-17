<?php

/*
 * This file is part of the Certificationy application.
 *
 * (c) Vincent Composieux <vincent.composieux@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Certificationy\Certification;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Loader
 *
 * @author Vincent Composieux <vincent.composieux@gmail.com>
 */
class Loader
{

    /**
     * Loads questions from yaml path
     *
     * @param array $categories
     *
     * @return Question[]
     */
    public static function load(array $categories, $path)
    {
        return self::mapQuestions(
            self::prepareFromYaml($categories, $path)
        );
    }

    /**
     * Get list of all categories
     *
     * @return array
     */
    public static function getCategories($path)
    {
        $categories = array();
        $files = self::prepareFromYaml(array(), $path);
        foreach ($files as $file) {
            $categories[] = $file['category'];
        }

        return array_unique($categories);
    }

    /**
     * Converts array data from yaml to Question objects
     *
     * @param array $data
     *
     * @return Question[]
     */
    protected static function mapQuestions(array $data)
    {
        $mapAnswer = function ($answerData) {
            return self::createAnswer($answerData['value'], $answerData['correct']);
        };
        $mapQuestion = function ($questionData) use ($mapAnswer) {
            try {
                $answers = array_map($mapAnswer, $questionData['answers']);
            } catch (\InvalidArgumentException $e) {
                throw new \ErrorException(
                    sprintf(
                        'Invalid answer format in question [%s]: %s',
                        $questionData['category'],
                        $questionData['question']
                    )
                );
            }

            return new Question(
                $questionData['question'],
                $questionData['category'],
                $answers
            );
        };

        return array_map($mapQuestion, $data);
    }

    protected static function createAnswer($value, $correct)
    {
        if (!is_bool($correct)) {
            throw new \InvalidArgumentException(
                sprintf('correct must be boolean in answer "%s"', $value)
            );
        }

        return new Answer($value, $correct);
    }

    /**
     * Prepares data from Yaml files and returns an array of questions
     *
     * @param array $categories : List of categories which should be included, empty array = all
     *
     * @return array
     */
    protected static function prepareFromYaml(array $categories, $path)
    {
        $data = array();
        $paths = Yaml::parse(file_get_contents($path))['paths'];
        foreach ($paths as $path) {
            $files = Finder::create()->files()->in($path)->name('*.yml');
            foreach ($files as $file) {
                $fileData = Yaml::parse($file->getContents());
                $category = $fileData['category'];
                if (count($categories) == 0 || in_array($category, $categories)) {
                    array_walk($fileData['questions'], function (&$item, $key) use ($category) {
                        $item['category'] = $category;
                    });
                    $data = array_merge($data, $fileData['questions']);
                }
            }
        }

        return $data;
    }
}
