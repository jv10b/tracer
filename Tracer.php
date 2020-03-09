<?php

/**
 * Class Tracer
 *
 * Clase para el cálculo de intervalos de tiempos en los procesos PHP
 *
 */
class Tracer
{
    //const OUTPUT_DIR = '/opt/usuarios/lema/www_lema_symlink/var/logs';
    const OUTPUT_DIR = '/var/www/html/var/logs';
    const TIME_PRECISION = 1000000; // precision de microsegundos
    const DECIMAL_PRECISION = 6; // precision de millonesimas
    const ENDLINE_CHAR = PHP_EOL;
    const TAB_SPACES = 4;

    /** @var Tracer */
    private static $instance;

    private $trace = [];

    private $currentSection = '';

    private $keys = [];

    private $debug = [];

    /**
     * singleton instance
     *
     * @return Tracer
     */
    public static function getInstance()
    {
        if (!self::$instance instanceof Tracer) {
            self::$instance = new Tracer();
        }

        return self::$instance;
    }

    /**
     * @param string $section
     *
     * @throws Exception
     */
    public function start($section)
    {
        $this->addKey($section);
        $this->trace[$section] = [];
        if (!empty($this->currentSection)) {
            $this->trace[$section]['parent'] = $this->currentSection;
        }
        $this->currentSection = $section;
        $this->lap('start');
    }

    /**
     * @param string $section
     */
    public function stop($section)
    {
        $this->trace[$section]['lap']['end'] = $this->timestamp();
        if (isset($this->trace[$section]['parent'])) {
            $this->currentSection = $this->trace[$section]['parent'];
        }
    }

    /**
     * @param string $lapName
     * @param string $iteratorkey
     *
     * @throws Exception
     */
    public function lap($lapName, $iteratorkey = '')
    {
        $this->addKey($lapName.$iteratorkey);
        $this->trace[$this->currentSection]['lap'][$lapName] = $this->timestamp();
    }

    /**
     * @param null $filename
     */
    public function stringOuput($filename = null)
    {
        $output = '';
        $tab = 0;
        foreach ($this->trace as $sectionName => $section) {
            $output .= $this->stringLine(
                sprintf(
                    'Section: %s',
                    $sectionName
                )
            );
            $tab = $tab + self::TAB_SPACES;
            $laps = $section['lap'];
            $end = false;
            while (false === $end) {
                $lapTime = current($laps);
                $lapName = key($laps);
                if ('start' !== key($laps)) {
                    $prevTime = prev($laps);
                    next($laps);
                    $output .= $this->stringLine(
                        sprintf(
                            '[%s] => %s',
                            $lapName,
                            $this->lapdiff($lapTime, $prevTime)
                        ),
                        $tab
                    );
                }
                if ('end' === $lapName) {
                    $end = true;
                }
                next($laps);
            }
            $output .= $this->stringLine(
                sprintf(
                    'Total: %s => %s',
                    $sectionName,
                    $this->lapdiff($section['lap']['end'], $section['lap']['start'])
                )
            );
        }

        $this->write2file($output, $filename);
    }

    /**
     * @param null $filename
     */
    public function csvOuput($filename = null)
    {
        $output = '';
        $totalTime = 0;
        foreach ($this->trace as $sectionName => $section) {
            $output .= $this->csvLine(
                sprintf(
                    'Sección: %s',
                    $sectionName
                ),
                '',
                ''
            );
            $laps = $section['lap'];
            $end = false;
            $totalLapTime = $this->lapdiff(
                $section['lap']['end'],
                $section['lap']['start']
            );
            if (0 === $totalTime) {
                $totalTime = $totalLapTime;
            }
            while (false === $end) {
                $lapTime = current($laps);
                $lapName = key($laps);
                if ('start' !== key($laps)) {
                    $prevTime = prev($laps);
                    next($laps);
                    $lapdiff = $this->lapdiff($lapTime, $prevTime);
                    $percent = round(($lapdiff * 100) / $totalTime, 2).'%';
                    $output .= $this->csvLine(
                        $lapName,
                        $lapdiff,
                        $percent
                    );
                }

                if ('end' === $lapName) {
                    $end = true;
                }
                next($laps);
            }
            $output .= $this->csvLine(
                sprintf(
                    'Total: %s',
                    $sectionName
                ),
                $totalLapTime,
                ''
            );
        }

        $this->write2file($output, $filename, 'csv');
    }

    /**
     * @return array
     */
    public function getDebug()
    {
        return $this->debug;
    }

    /**
     * @return array
     */
    public function getTraces()
    {
        return $this->trace;
    }

    /**
     * @param string $string
     * @param int    $tab
     *
     * @return string
     */
    private function stringLine($string, $tab = 0)
    {
        return sprintf(
            '%s%s%s',
            str_repeat(' ', $tab),
            $string,
            self::ENDLINE_CHAR
        );
    }

    /**
     *
     * @return string
     */
    private function csvLine()
    {
        return sprintf(
            '%s%s',
            implode(';', func_get_args()),
            self::ENDLINE_CHAR
        );
    }

    /**
     * Devuelve la diferencia en segudos como cadena
     *
     * @param float $lapTime
     * @param float $prevTime
     *
     * @return string
     */
    private function lapdiff($lapTime, $prevTime)
    {
        $diff = $lapTime - $prevTime;

        $this->debug[] = sprintf('%s - %s = %s', $lapTime, $prevTime, $diff);

        return number_format($diff / self::TIME_PRECISION, self::DECIMAL_PRECISION);
    }

    /**
     * Timestamp en microsegundos
     *
     * @return int
     */
    private function timestamp()
    {
        $mt = explode(' ', microtime());

        // sumamos el numero de segundos + el numero de microsegundos y lo manejamos como entero
        return (((int) $mt[1]) * self::TIME_PRECISION) + ((int) round($mt[0] * self::TIME_PRECISION));
    }

    /**
     * @param string $key
     *
     * @throws Exception
     */
    private function addKey($key)
    {
        if (true === array_search($key, $this->keys)) {
            throw new Exception(sprintf('El indice "%s" ya se ha utilizado', $this->keys));
        }
        $this->keys[] = $key;
    }


    /**
     * @param mixed  $content
     * @param string $filename
     * @param string $ext
     */
    private function write2file($content, $filename = 'trace', $ext = 'txt')
    {
        $content = (is_array($content)) ? print_r($content, true) : $content;
        $pathFile = sprintf(
            '%s/%s_%s.%s',
            self::OUTPUT_DIR,
            $filename,
            date('Y-m-d_H:i:s'),
            $ext
        );
        error_log($content, 3, $pathFile);
    }
}
