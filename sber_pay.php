<?

namespace PaySystem;

use Bitrix\Sale\Order;

class SberPay
{

    private const LOGIN = '*****';
    private const PASSWORD = '*****';
    private const SBER_URL = 'https://3dsec.sberbank.ru';

    /**
     * Проверка статуса оплаты заказа
     * @param $orderId
     * @return array
     */
    public static function getOrderStatus(int $orderId) : array
    {
        $params = [
            'userName' => self::LOGIN,
            'password' => self::PASSWORD,
            'orderId' => $orderId,
        ];

        $ch = curl_init(self::SBER_URL.'/payment/rest/getOrderStatusExtended.do?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, JSON_OBJECT_AS_ARRAY);

        return $response;
    }

    /**
     * Регистрация заказа в Сбере
     * @param $orderId
     * @return array
     */
    public function registerOrder(int $orderId) : array
    {
        $order = $this->getOrder($orderId);

        $params = [
            'userName' => self::LOGIN,
            'password' => self::PASSWORD,
            // rand() добавлено, т.к. Сбер регистрирует заказ и повторно получить ссылку на оплату нельзя
            'orderNumber' => $orderId.rand(),
            'orderBundle' => $order['cart'],
            'amount' => $order['amount'] * 100,
            'returnUrl' => $this->getReturnUrl([
                'ID' => $orderId,
            ]),
            'description' => "Заказ № {$orderId} на сайте example.ru",
        ];

        $ch = curl_init(self::SBER_URL.'/payment/rest/register.do?'.http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($result, JSON_OBJECT_AS_ARRAY);

        return $result;
    }

    /**
     * Получение корзины заказа, который был создан на сайте
     * @param $orderId
     * @return array
     */
    protected function getOrder(int $orderId) : array
    {
        $order = Order::load($orderId);
        $basket = $order->getBasket();
        $basketItems = $basket->getBasketItems();

        foreach ($basketItems as $num => $basketItem) {
            $cart[] = [
                'positionId' => $num + 1,
                'name' => $basketItem->getField('NAME'),
                'quantity' => array(
                    'value' => $basketItem->getQuantity(),
                    'measure' => 'шт',
                ),
                'itemAmount' => $basketItem->getFinalPrice() * 100,
                'itemCode' => $basketItem->getProductId(),
                'tax' => array(
                    'taxType' => 0,
                    'taxSum' => 0,
                ),
                'itemPrice' => $basketItem->getPrice() * 100,
            ];
        }
        $result['cart'] = json_encode(
            [
                'cartItems' => [
                    'items' => $cart,
                ],
            ],
            JSON_UNESCAPED_UNICODE
        );
        $result['amount'] = $order->getField("PRICE");

        return $result;
    }

    /**
     * Ссылка для возврата после оплаты
     * @param $params
     * @return string
     */
    public function getReturnUrl(array $params) : string
    {
        return implode('?', [
            'https://example.ru/account/orders-history/order_detail.php',
            http_build_query($params),
        ]);
    }

}