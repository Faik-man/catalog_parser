<?php
declare(strict_types = 1);
ini_set('error_reporting', E_ALL & ~E_WARNING);

function getElementsByClass($parentNode, string $tagName, string $className): array
{
    $nodes = array();

    $childNodeList = $parentNode->getElementsByTagName($tagName);
    for ($i = 0; $i < $childNodeList->length; $i++)
    {
        $node = $childNodeList->item($i);
        $classes = explode(' ', $node->getAttribute('class'));
        if (array_search($className, $classes) !== false) {
            $nodes[] = $node;
        }
    }

    return $nodes;
}

function getTextContent($node): string
{
    return trim($node->textContent);
}

function buildLink(string $hostName, string $catalogName, string $request = null): string
{
    return "{$hostName}/{$catalogName}/{$request}";
}

class CatalogParser {
    private string $catalogName;
    private int $pagesCount;
    private $dbConnection;

    const HOST_NAME = 'https://xn--b1agjaalfq5am6i.su';

    CONST CONFIG_FILE_NAME = 'db_config.txt';

    CONST BASE_TABLE_COLUMNS = [
        'Страна'                  => ['country', 'strval'],
        'Производитель'           => ['manufacturer', 'strval'],
        'Коллекция'               => ['collection', 'strval'],
        'Вид'                     => ['view', 'strval'],
        'Тип'                     => ['type', 'strval'],
        'Стиль'                   => ['style', 'strval'],
        'Цвет'                    => ['color', 'strval'],
        'Материал'                => ['material', 'strval'],
        'Общая мощность ламп'     => ['total_power_lamps', 'intval'],
        'Площадь освещения, (м2)' => ['lighting_area', 'intval'],
        'Гарантия'                => ['guarantee', 'intval']
    ];

    const ADDITIONAL_TABLE_COLUMNS = [
        'Место размещения'              => ['location', 'strval'],
        'Назначение'                    => ['purpose', 'strval'],
        'Класс влагозащиты'             => ['water_resistance_class', 'strval'],
        'Управление'                    => ['control', 'strval'],
        'Тип управления'                => ['type_control', 'strval'],
        'Пульт ДУ'                      => ['remote_control', 'strval'],
        'Выключатель в комплекте'       => ['switch_included', 'strval'],
        'Возможность подключить диммер' => ['possibility_connect_dimmer', 'strval'],
        'Совместимые лампы'             => ['compatible_lamps', 'strval'],
        'Ценовая группа'                => ['price_group', 'strval'],
        'Регулировка по высоте'         => ['height_adjustment', 'strval'],
        'Комплектация лампами'          => ['complete_with_lamps', 'strval'],
        'Лампы в комплекте'             => ['lamps_included', 'strval'],
        'Срок службы, часов'            => ['life_time', 'intval'],
        'Технические особенности'       => ['technical_features', 'strval'],
        'Теги'                          => ['tags', 'strval']
    ];

    const ELECTRICITY_TABLE_COLUMNS = [
        'Тип ламп'                     => ['type_lamp', 'strval'],
        'Мощность лампы'               => ['power_lamp', 'intval'],
        'Мощность'                     => ['power', 'intval'],
        'Мощность ламп, max'           => ['max_power_lamp', 'intval'],
        'Мощность ламп 2, max'         => ['max_power_lamp2', 'intval'],
        'Количество ламп'              => ['lamp_count', 'intval'],
        'Количество ламп 2'            => ['lamp_count2', 'intval'],
        'Тип цоколя'                   => ['type_socle', 'strval'],
        'Тип цоколя 2'                 => ['type_socle2', 'strval'],
        'Напряжение'                   => ['voltage', 'intval'],
        'Диммируемость'                => ['dimmability', 'strval'],
        'Аналог лампы накаливания, W'  => ['analogue_incandescent_lamp', 'intval'],
        'Цвет сечения'                 => ['glow_color', 'intval'],
        'Индекс цветопередачи, Ra'     => ['color_rendering_index', 'strval'],
        'Коэффициент пульсации, %'     => ['pulsation_coefficient', 'intval'],
        'Световой поток, lm'           => ['light_flow', 'intval'],
        'Световая температура'         => ['light_temperature', 'intval'],
        'Цветовая температура, K'      => ['colorful_temperature', 'intval'],
        'Диапазон рабочих температур'  => ['operating_temperature_range', 'strval'],
        'Угол рассеивания, градусов'   => ['scattering_angle', 'intval']
    ];

    const LAMPSHADE_TABLE_COLUMNS = [
        'Вид рассеивателя'             => ['type_diffuser', 'strval'],
        'Материал'                     => ['lampshade_material', 'strval'],
        'Цвет плафонов'                => ['lampshade_color', 'strval'],
        'Поверхность'                  => ['lampshade_surface', 'strval'],
        'Диаметр плафонов, см'         => ['lampshade_diameter', 'intval'],
        'Количество плафонов'          => ['lampshade_count', 'intval'],
        'Форма плафона'                => ['lampshade_shape', 'strval'],
        'Форма колбы'                  => ['flask_shape', 'strval'],
        'Направление'                  => ['direction', 'strval'],
        'Тип хрусталя'                 => ['type_crystal', 'strval']
    ];

    const ARMATURE_TABLE_COLUMNS = [
        'Материал'      => ['armature_material', 'strval'],
        'Цвет арматуры' => ['armature_color', 'strval'],
        'Поверхность'   => ['armature_surface', 'strval']
    ];

    const MONTAGE_TABLE_COLUMNS = [
        'Глубина врезки, см'              => ['cutting_depth', 'intval'],
        'Диаметр врезного отверстия, см'  => ['cut_in_hole_diameter',  'intval'],
        'Длина врезки, см'                => ['cutting_length', 'intval']
    ];

    const SIZES_TABLE_COLUMNS = [
        'Высота, см'                     => ['height', 'intval'],
        'Высота максимальная, см'        => ['max_height', 'intval'],
        'Высота минимальная, см'         => ['min_height', 'intval'],
        'Ширина, см'                     => ['width', 'intval'],
        'Длина, см'                      => ['length', 'intval'],
        'Длина подвеса, см'              => ['length_suspension', 'intval'],
        'Глубина, см'                    => ['depth', 'intval'],
        'Диаметр, см'                    => ['diameter', 'intval'],
        'Вес, кг'                        => ['weight', 'intval'],
        'Объем, м3'                      => ['volume', 'intval']
    ];

    const LOGISTICS_TABLE_COLUMNS = [
        'Высота коробки, см'  => ['box_height', 'intval'],
        'Ширина коробки, см'  => ['box_width',  'intval'],
        'Длина коробки, см'   => ['box_length', 'intval'],
        'Объем коробки,м3'    => ['box_volume', 'intval'],
        'Вес коробки, кг'     => ['box_weight', 'intval']
    ];

    function __construct($catalogName)
    {
        $this->catalogName = $catalogName;

        $html = $this->getHtmlFromRequest();
        assert($html !== false);

        $doc = $this->createDomOfPage($html);
        assert($doc !== false);

        $contentNode = $doc->getElementById('content');
        $classNodes = getElementsByClass($contentNode, 'a', 'pagin__link');

        $lastNode = $classNodes[count($classNodes) - 1];
        $request = $lastNode->getAttribute('href');
        $this->pagesCount = intval(trim($lastNode->textContent));

        list($db_hostname, $db_username, $db_password, $db_name) = $this->getDbConfig();

        try {
            $this->dbConnection = new mysqli($db_hostname, $db_username, $db_password, $db_name);
        } catch (Exception $e) {
            print_r($e->getMessage() . "\n");
            exit();
        } catch (Error $e) {
            print_r($e->getMessage() . "\n");
            exit();
        }

        $this->dbConnection->set_charset('utf8');

        $this->createTables();

        print_r("mysqli object constructed\n");
    }

    private function createTables()
    {
        $queryCreateTable = "CREATE TABLE IF NOT EXISTS products (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(100),
            code_product INT NOT NULL,
            vendor_code VARCHAR(20) NOT NULL,
            price_old INT,
            calc_price INT NOT NULL,
            images TEXT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE (title)
        )";

        if (!$this->dbConnection->query($queryCreateTable)) {
            print_r("Table creation failed: (" . $this->dbConnection->errno . ") " . $this->dbConnection->error . "\n");
        }

        $class = new ReflectionClass("CatalogParser");
        $constants = $class->getConstants();

        foreach ($constants as $name => $value) {
            if (str_ends_with($name, '_TABLE_COLUMNS')) {
                $nameTable = substr($name, 0, strpos($name, '_'));
                $this->createTable($nameTable, $value);
            }
        }
    }

    private function createTable($name, $columns): void
    {
        $name = strtolower($name);

        $query = "CREATE TABLE IF NOT EXISTS products_" . $name . " (
            id INT NOT NULL AUTO_INCREMENT,\n";

        foreach ($columns as $key => $value) {
            $row = $value[0] . " " . (($value[0] === 'intval') ? 'INT' : 'VARCHAR(255)') . ",\n";
            $query = $query . $row;
        }

        $query = "{$query} PRIMARY KEY (id), FOREIGN KEY (id) REFERENCES products(id) );";

        if (!$this->dbConnection->query($query)) {
            print_r("Table creation failed: (" . $this->dbConnection->errno . ") " . $this->dbConnection->error . "\n");
        }
    }

    private function getDbConfig(): array
    {
        $configHandle = fopen(self::CONFIG_FILE_NAME, 'r');
        assert(filesize(self::CONFIG_FILE_NAME) > 0);

        $content = fread($configHandle, filesize(self::CONFIG_FILE_NAME));
        $lines = explode("\n", $content);
        assert(count($lines) === 5);
        $lines = array_slice($lines, 0, 4);

        $warning = "Заполните файл " . self::CONFIG_FILE_NAME . " данными для подключения к бд.";
        foreach ($lines as $line) {
            assert($line[0] !== '#', $warning);
        }

        fclose($configHandle);

        return $lines;
    }

    public function getPagesCount(): int
    {
        return $this->pagesCount;
    }

    private function getHtmlFromLink(string $link): string|false
    {
        return file_get_contents($link);
    }

    private function getHtmlFromRequest(string $request = null): string|false
    {
        $link = buildLink(self::HOST_NAME, $this->catalogName, $request);
        return file_get_contents($link);
    }

    private function createDomOfPage(string $html): DOMDocument|false
    {
        $doc = new DOMDocument();
        if ($doc->loadHTML($html)) {
            return $doc;
        }

        return false;
    }

    private function getProductCardHeaders(DOMDocument $doc): array
    {
        $contentNode = $doc->getElementById('content');
        $result = getElementsByClass($contentNode, 'div', 'product-card-header');
        return $result;
    }

    private function parseProductDescLines($productDescLines, $columns): array
    {
        $result = array();
        foreach ($productDescLines as $line) {
            $titleDiv = getElementsByClass($line, 'span', 'product__text')[0];
            $title = getTextContent($titleDiv);
            $title = mb_substr($title, 0, mb_strlen($title) - 1);

            assert(isset($columns[$title]));
            $column = $columns[$title];
            assert($column !== null);

            $valueDiv = getElementsByClass($line, 'div', 'product__text')[0];
            $value = getTextContent($valueDiv);

            list($columnName, $columnType) = $column;
            $result[$columnName] = [strval($columnType), $columnType($value)];
        }
        return $result;
    }

    private function parseProductDescBase($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::BASE_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('base', $result);
    }

    private function parseProductDescElectricity($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::ELECTRICITY_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('electricity', $result);
    }

    private function insertIntoProductParamDb($nameTable, array $paramInfo): bool
    {
        $query = "INSERT INTO products_" . $nameTable . ' (';
        $keys = array_keys($paramInfo);
        $query = $query . join(', ', $keys) . ')';

        $query = $query . ' VALUES (' . join(',', array_values(array_fill(0, count($paramInfo), '?'))) . ')';
        $stmt = $this->dbConnection->prepare($query);

        $getValue = function($p) {
            return $p[1];
        };
        $values = array_map($getValue, array_values($paramInfo));
        var_dump($values);

        $cb = function($p) {
            $type = $p[0];
            assert($type === 'strval' || $type === 'intval');
            return ($type === 'strval') ? 's' : 'd';
        };

        $types = join('', array_map($cb, array_values($paramInfo)));

        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        assert(1 === $stmt->affected_rows);

        return $result;
    }

    private function parseProductDescLampshadeParam($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::LAMPSHADE_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('lampshade', $result);
    }

    private function parseProductDescArmatureParam($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::ARMATURE_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('armature', $result);
    }

    private function parseProductDescSizes($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::SIZES_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('sizes', $result);
    }

    private function parseProductDescMontage($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::MONTAGE_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('montage', $result);
    }

    private function parseProductDescLogistics($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::LOGISTICS_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('logistics', $result);
    }

    private function parseProductDescAdditional($productDescLines): void
    {
        $result = $this->parseProductDescLines($productDescLines, self::ADDITIONAL_TABLE_COLUMNS);
        $this->insertIntoProductParamDb('additional', $result);
    }

    private function parseProductDescList($contentNode): void
    {
        $productDescList = getElementsByClass($contentNode, 'div', 'product-desc-list')[0];

        $productDescItems = getElementsByClass($productDescList, 'div', 'product-desc-item');

        $arr = [
            'Основные'                 => array($this, 'parseProductDescBase'),
            'Электрика'                => array($this, 'parseProductDescElectricity'),
            'Параметры плафонов'       => array($this, 'parseProductDescLampshadeParam'),
            'Параметры арматуры'       => array($this, 'parseProductDescArmatureParam'),
            'Размеры'                  => array($this, 'parseProductDescSizes'),
            'Логистика'                => array($this, 'parseProductDescLogistics'),
            'Монтаж'                   => array($this, 'parseProductDescMontage'),
            'Дополнительные параметры' => array($this, 'parseProductDescAdditional'),
        ];

        foreach ($productDescItems as $item) {
            $productDescItemTitle = getTextContent(getElementsByClass($item, 'h2', 'product__title')[0]);
            print_r(" {$productDescItemTitle} \n");

            $func = $arr[$productDescItemTitle];
            assert($func !== null);

            $productDescLines = getElementsByClass($item, 'div', 'product-desc-line');
            $descBase = $func($productDescLines);
        }
    }

    private function getPathImages($contentNode): string
    {
        $sliderThumbs = getElementsByClass($contentNode, 'div', 'product-info-slider-thumbs')[0];
        assert($sliderThumbs !== null);

        $divImages = getElementsByClass($sliderThumbs, 'div', 'gallery-slider__image');
        assert(count($divImages) > 0);

        $images = array();
        foreach ($divImages as $div) {
            $img = $div->getElementsByTagName('img')->item(0);
            $src = $img->getAttribute('src');
            assert(str_ends_with($src, '.webp'));

            $images[] = $src;
        }

        return join("\n", $images);
    }

    private function getProductMainInfo($contentNode): array
    {
        $productHeadingNode = getElementsByClass($contentNode, 'h1', 'product__heading')[0];
        $productHeading = strval(trim($productHeadingNode->textContent));
        print_r("Heading {$productHeading}\n");

        $productSidebarInfo = getElementsByClass($contentNode, 'div', 'product-sidebar-info')[0];

        $productSidebarTexts = getElementsByClass($productSidebarInfo, 'span', 'product-sidebar__span');
        assert(count($productSidebarTexts) === 2);

        print_r(getTextContent($productSidebarTexts[0]) . "\n");
        print_r(getTextContent($productSidebarTexts[1]) . "\n");

        $codeProduct = intval(getTextContent($productSidebarTexts[0]));
        print_r($codeProduct . "\n");
        assert($codeProduct > 0);

        $vendorCode = strval(getTextContent($productSidebarTexts[1]));
        print_r($vendorCode . "\n");

        $productSidebarCalc = getElementsByClass($contentNode, 'div', 'product-sidebar-calc')[0];

        $priceOld = null;
        $priceOldNodes = getElementsByClass($productSidebarCalc, 'div', 'product-card__price-old');
        if (!empty($priceOldNodes))
        {
            $priceOldNodeText = getTextContent($priceOldNodes[0]);
            $priceOldNodeText = mb_convert_encoding(mb_substr($priceOldNodeText, 0, mb_strlen($priceOldNodeText) - 1), 'ASCII');

            $priceOldNodeText = str_replace("?", '', $priceOldNodeText);
            $priceOld = intval($priceOldNodeText);
        }

        $calcPriceText = getTextContent(getElementsByClass($contentNode, 'div', 'product-sidebar-calc__price')[0]);
        $calcPriceText = mb_convert_encoding(mb_substr($calcPriceText, 0, mb_strlen($calcPriceText) - 1), 'ASCII');

        $calcPrice = intval(str_replace("?", '', $calcPriceText));

        $images = $this->getPathImages($contentNode);

        $result = [
            'title' => $productHeading,
            'price_old' => $calcPrice,
            'calc_price' => $calcPrice,
            'code_product' => $codeProduct,
            'vendor_code' => $vendorCode,
            'images' => $images
        ];

        return $result;
    }

    private function insertIntoProductDb(array $productMainInfo): bool
    {
        list($title,
            $codeProduct,
            $vendorCode,
            $priceOld,
            $calcPrice,
            $images) = array_values($productMainInfo);

        $queryInsert = "INSERT INTO products (title, code_product, vendor_code, price_old, calc_price, images) 
            VALUES (?, ?, ?, ?, ?, ?)";

        print_r($codeProduct . "\n");
        $stmt = $this->dbConnection->prepare($queryInsert);

        $stmt->bind_param("sdddds", $title, $codeProduct, $vendorCode, $priceOld, $calcPrice, $images);
        $result = $stmt->execute();
        assert(1 === $stmt->affected_rows);

        return $result;
    }

    public function work(): void
    {
        $pagesRange = range(0, $this->pagesCount - 1);
        foreach ($pagesRange as $indexPage) {
            $request = '?p=' . $indexPage;
            $html = $this->getHtmlFromRequest($request);
            assert($html !== false);

            $doc = $this->createDomOfPage($html);
            assert($html !== false);

            $productCardHeaders = $this->getProductCardHeaders($doc);
            assert(count($productCardHeaders) > 0);

            foreach ($productCardHeaders as $cardHeader) {
                $aTags = $cardHeader->getElementsByTagName('a');
                assert(count($aTags) === 1);

                $link = $aTags[0]->getAttribute('href');

                $htmlProduct = $this->getHtmlFromLink($link);
                assert($htmlProduct !== false);

                $doc = $this->createDomOfPage($htmlProduct);
                $contentNode = $doc->getElementsByTagName('main')->item(0);

                $productMainInfo = $this->getProductMainInfo($contentNode);
                assert(true === $this->insertIntoProductDb($productMainInfo));

                $this->parseProductDescList($contentNode);
            }

            if ($indexPage !== $this->pagesCount - 1) {
                print_r("Парсинг " . ($indexPage + 1) . "-й страницы завершен!\n");
            }
        }
    }
}

assert($argc === 2, 'Нужно передать в скрипт имя каталога');
$catalogName = $argv[1];

$parser = new CatalogParser($catalogName);
print_r('Обнаружено ' . $parser->getPagesCount() . " страниц(ы) в каталоге.\n");

print_r("Дождитесь окончания парсинга!\n");
$parser->work();

print_r('Парсинг завершен!');
