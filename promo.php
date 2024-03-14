<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");
$APPLICATION->SetPageProperty("tags", "промокод");
$APPLICATION->SetPageProperty("keywords_inner", "Промокод, акция, получи скидку");
$APPLICATION->SetPageProperty("title", "Промо");
$APPLICATION->SetTitle("Промо");
?>
<?php
global $USER;
if ($USER->IsAuthorized()) {
    // маска для купона и название правила для скидки
    $mask = "PROMO2024-" . $USER->GetID();
    // проверка есть ли в базу ранее полученный купон
    $check_exists = Bitrix\Sale\Internals\DiscountTable::getList(
        [
            "filter" => [
                "name" => "$mask"
            ]
        ]
    )->Fetch();
    $check_coupon = empty($_POST['checkDiscount']) ? '' : $_POST['checkDiscount'];
    ?>
    <div class="container">
        <div class="row">
            <div class="col">
                <form action="/promo.php" method="post">
                    <button type="submit" name="getDiscount" value="1" class="btn btn-primary">Получить скидку
                    </button>
                </form>
            </div>
            <div class="col">
                <form action="/promo.php" method="post" class="form-inline">
                    <div class="form-group row">
                        <div class="col">
                            <input type="text" name="checkDiscount" class="form-control" value="<?=$check_coupon?>"
                                   placeholder="Введите код скидки">
                        </div>
                        <div class="col">
                            <button type="submit" value="1" class="btn btn-primary">Проверить скидку</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    // если купона нет
    if (!empty($_POST['getDiscount'])) {
        if (empty($check_exists)) {
            // генерируем купон на скидку
            $promo_code = $mask . "-" . \Bitrix\Main\Security\Random::getStringByAlphabet(4, \Bitrix\Main\Security\Random::ALPHABET_ALPHAUPPER);
            // определяем размер скидки
            $discount = mt_rand(1, 50);
            $datetime = new \Bitrix\Main\Type\DateTime();
            $by_time = $datetime->toString();
            $to_time = $datetime->add("3 hour")->toString();
            // добавляем правило на использование скидки в корзине
            $arFields = array(
                'LID' => 's1',
                'NAME' => $mask, // название скидки по маске
                'ACTIVE_FROM' => $by_time, // время действия от
                'ACTIVE_TO' => $to_time, // время действия до
                'ACTIVE' => 'Y',
                'SORT' => '100',
                'PRIORITY' => '1',
                'LAST_DISCOUNT' => 'Y',
                'LAST_LEVEL_DISCOUNT' => 'N',
                'XML_ID' => '',
                'CONDITIONS' =>
                    array(
                        'CLASS_ID' => 'CondGroup',
                        'DATA' =>
                            array(
                                'All' => 'AND',
                                'True' => 'True',
                            ),
                        'CHILDREN' => array(),
                    ),
                'ACTIONS' =>
                    array(
                        'CLASS_ID' => 'CondGroup',
                        'DATA' =>
                            array(
                                'All' => 'AND',
                            ),
                        'CHILDREN' =>
                            array(
                                0 =>
                                    array(
                                        'CLASS_ID' => 'ActSaleBsktGrp',
                                        'DATA' =>
                                            array(
                                                'Type' => 'Discount',
                                                'Value' => $discount, // размер скидки
                                                'Unit' => 'Perc',
                                                'Max' => 0,
                                                'All' => 'AND',
                                                'True' => 'True',
                                            ),
                                        'CHILDREN' => array(),
                                    ),
                            ),
                    ),
                'USER_GROUPS' => [6], // скидка для группы зарегистрированных пользователей
            );
            $action_id = CSaleDiscount::Add($arFields);
            // добавление купона на
            $arCouponFields = array(
                'DISCOUNT_ID' => $action_id, // правила корзины для этого купона (созданые выше)
                'ACTIVE_FROM' => \Bitrix\Main\Type\Date::createFromText($by_time), // время старта действия купона
                'ACTIVE_TO' => \Bitrix\Main\Type\Date::createFromText($to_time), // время окончание действия купона
                'TYPE' => \Bitrix\Sale\Internals\DiscountCouponTable::TYPE_ONE_ORDER,
                'MAX_USE' => 1,
                'COUPON' => $promo_code,
                'USER_ID' => $USER->GetID(),
            );
            $result = \Bitrix\Sale\Internals\DiscountCouponTable::add($arCouponFields);
            $expired = false;
        } else {
            $discount = $check_exists["SHORT_DESCRIPTION_STRUCTURE"]["VALUE"];
            $coupon = \Bitrix\Sale\Internals\DiscountCouponTable::getList([
                "filter" => [
                    "DISCOUNT_ID" => $check_exists["ID"]
                ]
            ])->Fetch();
            $promo_code = $coupon["COUPON"];
            $by_time = $coupon["ACTIVE_FROM"];
            $to_time = $coupon["ACTIVE_TO"];
        }
        if ((new DateTime("now"))->format('Y-m-d H:i:s') < $to_time) {
            ?>
            <div class="alert alert-danger mt-3" role="alert">
                Скидка недостпуна
            </div>
            <?php
        } else {
            ?>
            <div class="alert alert-success mt-3" role="alert">
                Ваша скидка <b><?= $discount ?>%</b> по купону <b><?= $promo_code ?></b> действует c
                <b><?= $by_time ?></b> до <b><?= $to_time ?></b>
            </div>
            <?php
        }
    }
    // проверка введенного в форму купона
    if (!empty($check_coupon)) {
        $coupon = \Bitrix\Sale\Internals\DiscountCouponTable::getList([
            "filter" => [
                "COUPON" => $check_coupon
            ]
        ])->Fetch();
        // если нет такого купона или он не принадлежит текущему пользователю, то выдаем ошибку
        if (empty($coupon) || $coupon["USER_ID"] != $USER->GetID()) {
            ?>
            <div class="alert alert-danger mt-3" role="alert">
                Скидка недоступна
            </div>
            <?php
        }
        // иначе выводим информацию по текущему купону
        else {
            $check_rule = Bitrix\Sale\Internals\DiscountTable::getById($coupon["DISCOUNT_ID"])->Fetch();
            $discount = $check_rule["SHORT_DESCRIPTION_STRUCTURE"]["VALUE"];
            $promo_code = $coupon["COUPON"];
            $by_time = $coupon["ACTIVE_FROM"];
            $to_time = $coupon["ACTIVE_TO"];
            ?>
            <div class="alert alert-success mt-3" role="alert">
                Ваша скидка <b><?= $discount ?>%</b> по купону <b><?= $promo_code ?></b> действует c
                <b><?= $by_time ?></b> до <b><?= $to_time ?></b>
            </div>
            <?php
        }
    }
} else {
    echo "Для получения промокода авторизуйтесь на сайте";
    $APPLICATION->IncludeComponent(
        "bitrix:system.auth.form",
        ".default",
        array(
            "REGISTER_URL" => "/personal/register.php",
            "FORGOT_PASSWORD_URL" => "/personal/profile/?forgot_password=yes",
            "PROFILE_URL" => "/personal/profile/",
            "SHOW_ERRORS" => "Y"
        )
    );
}
?>
<? require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php"); ?>