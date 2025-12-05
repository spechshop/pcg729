<?php

declare(strict_types=1);

namespace ZM\Logger;

/**
 * 输出漂亮的表格参数形式，支持自适应终端大小
 *
 * Class ConsolePrettyPrinter
 */
class TablePrinter
{
    /**
     * @var array 参数列表
     */
    protected $params;

    /**
     * @var string 顶部格式
     */
    protected $head;

    /**
     * @var string 底部格式
     */
    protected $foot;

    /**
     * @var int 边界宽度
     */
    protected $border_width;

    /**
     * @var string 值颜色
     */
    protected $value_color = 'green';

    /**
     * @var bool 是否超出宽度自动省略
     */
    protected $row_overflow_hide = false;

    protected $terminal_size;

    public function __construct(array $params, string $head = '=', string $foot = '=', int $max_border_length = 79)
    {
        $this->params = $params;
        $this->head = $head;
        $this->foot = $foot;
        $this->setBorderWidth($max_border_length);
    }

    /**
     * 设置值的显示颜色
     *
     * @param  string $color 颜色
     * @return $this  返回当前对象
     */
    public function setValueColor(string $color): TablePrinter
    {
        if ($color === 'random') {
            $random_list = [
                'red', 'green', 'blue', 'yellow', 'magenta', 'gray',
                'bright_red', 'bright_yellow', 'bright_green', 'bright_blue', 'bright_magenta', 'bright_cyan',
            ];
            $random = array_rand($random_list);
            $this->value_color = $random_list[$random];
        } else {
            $this->value_color = $color;
        }
        return $this;
    }

    /**
     * 设置是否超出宽度自动省略
     *
     * @param  bool  $hide 是否超出宽度自动省略
     * @return $this 返回当前对象
     */
    public function setRowOverflowHide(bool $hide = true): TablePrinter
    {
        $this->row_overflow_hide = $hide;
        return $this;
    }

    /**
     * 打印表格
     */
    public function printAll(): void
    {
        $this->printHead();
        $this->printBody();
        $this->printFoot();
    }

    /**
     * 打印表格头
     */
    public function printHead(): void
    {
        echo $this->head . PHP_EOL;
    }

    /**
     * 打印表格尾
     */
    public function printFoot(): void
    {
        echo $this->foot . PHP_EOL;
    }

    /**
     * 打印表格体
     */
    public function printBody()
    {
        $line_data = [];
        $current_line = 0;
        foreach ($this->params as $k => $v) {
            $k = (string) $k;
            $v = (string) $v;
            $k_len = mb_strwidth($k); // 获取 key 的宽度
            $v_len = mb_strwidth($v); // 获取 value 的宽度
            $len = $k_len + 2 + $v_len; // 计算需要的宽度
            $valid_width = $this->border_width - 2; // 获取可用的宽度
            if ($k_len + 5 > $this->border_width - 2) { // 这个参数的 key 过长了，没有你这么用的！！
                continue;
            }
            while (true) {
                if (!isset($line_data[$current_line])) { // 如果行是空的，先尝试放入一个参数
                    if ($len > $valid_width) { // 需要的宽度超出一行，直接另起折行
                        if ($this->row_overflow_hide) { // 如果开启了超出部分隐藏，则直接砍掉后面的东西，变成三个或四个点
                            [$partial_v, $partial_v_len] = $this->getPartialValue($v, $valid_width - $k_len - 2);

                            // 写入到数据中
                            $line_data[$current_line] = [
                                'used' => $valid_width - $k_len - 2 - $partial_v_len,
                                'can_put_second' => false,
                                'lines' => $k . ': '
                                    . ConsoleColor::apply([$this->value_color], $partial_v . str_pad('', $valid_width - $k_len - 2 - $partial_v_len, '.')),
                            ];
                            ++$current_line;
                        // 下一个参数
                        } else { // 没开隐藏就要拐弯输出
                            $line_data[$current_line] = [
                                'used' => $k_len + 2,
                                'can_put_second' => false,
                                'lines' => $k . ': ',
                            ];
                            do {
                                [$partial_v, $partial_v_len, $next_offset] = $this->getPartialValue($v, $valid_width - $line_data[$current_line]['used'], 0);
                                $v = mb_substr($v, $next_offset);
                                $line_data[$current_line]['lines'] .= ConsoleColor::apply([$this->value_color], $partial_v);
                                $line_data[$current_line]['used'] += $partial_v_len;
                                ++$current_line;
                                $line_data[$current_line] = [
                                    'used' => 0,
                                    'can_put_second' => false,
                                    'lines' => '',
                                ];
                            } while ($v !== '');
                            if ($line_data[$current_line]['used'] === 0) {
                                unset($line_data[$current_line]);
                            }
                            // 下一个参数
                        }
                        break;
                    }
                    // 没超出一行，直接写
                    $line_data[$current_line] = [
                        'used' => $len,
                        'can_put_second' => ($valid_width >= 57 && intval(floor($valid_width / 2)) - 2 + ($valid_width % 2) > $len),
                        'lines' => $k . ': ' . ConsoleColor::apply([$this->value_color], $v),
                    ];
                    break;
                }
                // 如果当前行不是空的，就要看看是否能放下这个参数
                if ($line_data[$current_line]['can_put_second'] && ($k_len + $v_len + 2 <= floor($valid_width / 2) - 2)) { // 如果可以放下，就放下
                    // 首先把前面的参数补充到中间的分隔符
                    $line_data[$current_line]['lines'] .= str_pad('', intval(floor($valid_width / 2)) - 2 + ($valid_width % 2) - $line_data[$current_line]['used']);
                    // 然后输出分隔符
                    $line_data[$current_line]['lines'] .= ' |  ';
                    // 最后输出第二列的参数
                    $line_data[$current_line]['lines'] .= $k . ': ' . ConsoleColor::apply([$this->value_color], $v);
                    ++$current_line;
                    break;
                }   // 放不下，直接下一轮继续
                ++$current_line;
            } // 这层是 while(true)
        }
        foreach ($line_data as $line) {
            echo ' ' . $line['lines'] . PHP_EOL;
        }
    }

    /**
     * 设置边界最大宽度，不调用本函数的话默认为79
     *
     * @param  int   $border_width 边界最大宽度
     * @return $this 返回当前对象
     */
    public function setBorderWidth(int $border_width): TablePrinter
    {
        if ($border_width <= 0) {
            $this->border_width = $this->fetchTerminalSize();
        } else {
            $terminal_size = $this->fetchTerminalSize();
            $this->border_width = min($border_width, $terminal_size);
        }
        $this->head = str_pad('', $this->border_width, $this->head[0]);
        $this->foot = str_pad('', $this->border_width, $this->foot[0]);
        return $this;
    }

    /**
     * 获取终端大小
     *
     * @return int 终端大小
     */
    public function fetchTerminalSize(): int
    {
        if (!isset($this->terminal_size)) {
            /* @phpstan-ignore-next-line */
            if (STDIN === false) {
                return $this->terminal_size = 79;
            }
            $size = 0;
            if (DIRECTORY_SEPARATOR === '\\') {
                exec('mode con', $out);
                foreach ($out as $v) {
                    if (strpos($v, 'Columns:') !== false) {
                        $num = trim(explode('Columns:', $v)[1]);
                        $size = intval($num);
                        break;
                    }
                }
            } else {
                $size = exec('stty size 2>/dev/null');
                // in case stty is not available
                if (empty($size)) {
                    $size = 0;
                } else {
                    $size = (int) explode(' ', trim($size))[1];
                }
            }
            if (empty($size)) {
                return $this->terminal_size = 79;
            }
            return $this->terminal_size = $size;
        }
        return $this->terminal_size;
    }

    /**
     * 获取字符串的截断部分、占用宽度、截断偏移量
     *
     * @param  string $v           字符串
     * @param  int    $valid_width 有效宽度
     * @param  int    $remain      需要预留的宽度
     * @return array  返回截断部分、占用宽度、截断偏移量
     */
    private function getPartialValue(string $v, int $valid_width, int $remain = 3): array
    {
        $virtual_v_offset = 0;
        do { // 依次填入字符，直到空下了小于等于4宽度的距离
            ++$virtual_v_offset;
            $virtual_v = mb_substr($v, 0, $virtual_v_offset);
            $used_len = mb_strwidth($virtual_v);
        } while ($valid_width - $used_len > $remain && mb_strlen($virtual_v) < mb_strlen($v));
        return [$virtual_v, $used_len, $virtual_v_offset];
    }
}
