<?php


class CoreCalendar
{
    /**
     * Total de dias que poderao ser executados depois do inicio do trimestre
     */
    const QTD_DAY_EXEC_QUARTER = 3;

    /**
     * Trimestre usado para envio de dados a cvm
     */
    const QUARTERS = [
        [
            'quarter' => 1,
            'month_exec' => 1,
            'months' => [1, 2, 3]
        ],
        [
            'quarter' => 2,
            'month_exec' => 4,
            'months' => [4, 5, 6]
        ],
        [
            'quarter' => 3,
            'month_exec' => 7,
            'months' => [7, 8, 9]
        ],
        [
            'quarter' => 4,
            'month_exec' => 10,
            'months' => [10, 11, 12]
        ],
    ];

    /* @var Carbon|null */
    protected $date;
    /* @var string */
    protected $year;
    /** @var string */
    protected $month;
    /* @var string */
    protected $day;
    /** @var string */
    protected $today;
    /** @var Collection */
    protected $quarters;
    /** @var mixed */
    protected $month_exec;
    /** @var array */
    protected $days_exec;
    /** @var string */
    protected $warning_day;
    /** @var mixed */
    protected $present_quarter;
    /** @var mixed */
    protected $first_date_quarter;
    /** @var mixed */
    protected $last_date_quarter;
    /** @var mixed */
    protected $present_months_quarter;
    /** @var string */
    protected $present_day_start_quarter;
    /** @var string */
    protected $present_day_end_quarter;
    /** @var int|mixed */
    protected $before_quarter;
    /** @var mixed */
    protected $before_month_exec;
    /** @var mixed */
    protected $before_month_quarter;

    /**
     * Calendar constructor.
     * @param Carbon|null $date
     * @throws Exception
     */
    public function __construct(Carbon $date = null)
    {
        if (empty($date)) {
            $date = Carbon::now();
        }

        //dados da data atual
        $this->date = $date;
        $this->today = $date->format('Y-m-d');
        $this->day = $this->date->format('d');
        $this->month = $this->date->format('m');
        $this->year = $this->date->format('Y');
        $this->quarters = collect(self::QUARTERS);

        //trimestre atual
        $this->quarters->each(function ($item, $key) {
            if (in_array($this->month, $item['months'])) {
                $this->present_quarter = $item['quarter'];
            }
        });

        //trimestre atual
        $this->present_months_quarter = $this->quarters->where('quarter', $this->present_quarter)->pluck('months')->last();
        $this->present_day_start_quarter = $date->startOfMonth()->format('d');
        $this->present_day_end_quarter = $date->endOfMonth()->format('d');
        $this->first_date_quarter = Carbon::create($this->year, $this->present_months_quarter[0], $this->present_day_start_quarter)->format('Y-m-d');
        $this->last_date_quarter = Carbon::create($this->year, $this->present_months_quarter[2], $this->present_day_end_quarter)->format('Y-m-d');

        //trimestre anterior
        $this->before_quarter = ($this->present_quarter - 1) < 1 ? 4 : $this->present_quarter - 1;
        $this->before_month_quarter = $this->quarters->where('quarter', $this->before_quarter)->pluck('months')->last();
        $this->before_month_exec = $this->quarters->where('quarter', $this->before_quarter)->pluck('month_exec')->last();

        //exec trimestre
        $this->days_exec = $this->getDayValid(Carbon::create($this->year, $this->present_months_quarter[0], 1), self::QTD_DAY_EXEC_QUARTER);
        $this->month_exec = $this->quarters->where('quarter', $this->present_quarter)->pluck('month_exec')->last();
        $this->warning_day = Carbon::parse($this->days_exec->last())->addDay()->format('d/m/Y');
    }

    /**
     * @return int
     */
    public function getDay(): int
    {
        return (int)$this->day;
    }

    /**
     * @return Carbon|null
     */
    public function getDate(): ?Carbon
    {
        return $this->date;
    }

    /**
     * @return string
     */
    public function getYear(): string
    {
        return $this->year;
    }

    /**
     * @return string
     */
    public function getMonth(): string
    {
        return $this->month;
    }

    /**
     * @param string $date_start
     * @param string $date_end
     * @return Collection
     * @throws Exception
     */
    public function getPeriodValid(string $date_start, string $date_end)
    {
        $result = [];
        $day = date($date_start);

        //convertando os valores informados para verificar se é um data valida
        if (!$this->validateDate($date_start) || !$this->validateDate($date_end)) {
            throw new Exception('Datas inválidas, formato deve ser Y-m-d.');
        }

        while ($day <= "$date_end") {
            $day = Carbon::parse($day);

            if (!$this->isHoliday($day->format('Y-m-d')) && $this->isBusinessDay($day->format('Y-m-d'))) {
                $result[$day->format('Y-m-d')] = $day->format('Y-m-d');
            }

            $day->addDay();

            if (!$this->isHoliday($day->format('Y-m-d')) && $this->isBusinessDay($day->format('Y-m-d'))) {
                $result[$day->format('Y-m-d')] = $day->format('Y-m-d');
            }
        }

        return collect(array_values($result));
    }


    /**
     * @param $date_init
     * @param int $of_days
     * @return Collection
     * @throws Exception
     */
    function getDayValid($date_init, $of_days = 30)
    {
        $dateTime = new DateTime($date_init);

        $listaDiasUteis = [];
        $contador = 0;
        while ($contador < $of_days) {
            $dateTime->modify('+1 weekday'); // adiciona um dia pulando finais de semana
            $data = $dateTime->format('Y-m-d');
            if (!$this->isHoliday($data)) {
                $listaDiasUteis[] = $data;
                $contador++;
            }
        }

        return collect($listaDiasUteis);
    }

    /**
     * @param $date
     * @return bool
     */
    function isHoliday($date)
    {
        $listaFeriado = $this->holidays('Y-m-d')->toArray();
        if (in_array($date, $listaFeriado)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $check_date
     * @return string
     * @throws Exception
     */
    public function isBusinessDay(string $check_date)
    {
        $date = new DateTime($check_date);
        $date->modify('+0 weekday');

        if ($date->format('Y-m-d') == $check_date) {
            return true;
        }

        return false;
    }

    /**
     * @param string $date
     * @param int $add_day
     * @return bool
     * @throws Exception
     */
    public function nextBusinessDay(string $date, int $add_day = 1)
    {
        $date = new DateTime($date);
        $date->modify("+{$add_day} weekday");

        if ($date->format('Y-m-d') == $date) {
            return true;
        }

        return false;
    }

    /**
     * @param string $format
     * @return Collection
     */
    public function holidays($format = 'd/m/Y')
    {
        $result = [];
        foreach ($this->daysHolidays() as $feriado) {
            $result[] = Carbon::parse($feriado)->format($format);
        }
        return collect($result)->sortBy($result);
    }

    /**
     * @param null $ano
     * @return array
     */
    private function daysHolidays($ano = null)
    {
        if ($ano === null) {
            $ano = intval(date('Y'));
        }

        $pascoa = easter_date($ano); // Limite de 1970 ou após 2037 da easter_date PHP consulta http://www.php.net/manual/pt_BR/function.easter-date.php
        $dia_pascoa = date('j', $pascoa);
        $mes_pascoa = date('n', $pascoa);
        $ano_pascoa = date('Y', $pascoa);

        $feriados = [
            // Tatas Fixas dos feriados Nacionail Basileiras
            mktime(0, 0, 0, 1, 1, $ano), // Confraternização Universal - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 4, 21, $ano), // Tiradentes - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 5, 1, $ano), // Dia do Trabalhador - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 9, 7, $ano), // Dia da Independência - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 10, 12, $ano), // N. S. Aparecida - Lei nº 6802, de 30/06/80
            mktime(0, 0, 0, 11, 2, $ano), // Todos os santos - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 11, 15, $ano), // Proclamação da republica - Lei nº 662, de 06/04/49
            mktime(0, 0, 0, 12, 25, $ano), // Natal - Lei nº 662, de 06/04/49

            // These days have a date depending on easter
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 48, $ano_pascoa), //2ºfeira Carnaval
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 47, $ano_pascoa), //3ºfeira Carnaval
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa - 2, $ano_pascoa), //6ºfeira Santa
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa, $ano_pascoa), //Pascoa
            mktime(0, 0, 0, $mes_pascoa, $dia_pascoa + 60, $ano_pascoa), //Corpus Christi
        ];

        sort($feriados);

        return $feriados;
    }

    /**
     * @return mixed
     */
    public function getPresentQuarter()
    {
        return $this->present_quarter;
    }

    /**
     * @return mixed
     */
    public function getFirstDateQuarter()
    {
        return $this->first_date_quarter;
    }

    /**
     * @return mixed
     */
    public function getLastDateQuarter()
    {
        return $this->last_date_quarter;
    }

    /**
     * @param string $format
     * @return string
     */
    public function getToday($format = 'Y-m-d'): string
    {
        if ($format) {
            return Carbon::parse($this->today)->format($format);
        }

        return $this->today;
    }

    /**
     * @return array
     */
    public function getDaysExec(): array
    {
        return $this->days_exec->toArray();
    }

    /**
     * @param string $format
     * @return string
     */
    public function getWarningDay($format = 'Y-m-d'): string
    {
        if ($format) {
            return Carbon::parse($this->warning_day)->format($format);
        }

        return $this->warning_day;
    }

    /**
     * @param string $date
     * @param string $format
     * @return bool
     */
    function validateDate(string $date, string $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }
}