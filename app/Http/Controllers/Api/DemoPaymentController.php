<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoPaymentController extends Controller
{
    // 綠界測試環境憑證（公開測試帳號）
    private string $merchantId = '2000132';
    private string $hashKey    = '5294y06JbISpM5x9';
    private string $hashIV     = 'v77hoKGq4kWxNNIS';
    private string $paymentUrl = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';

    /**
     * 前端送出購物車 → 產生綠界付款參數
     */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'items'         => 'required|array|min:1',
            'items.*.name'  => 'required|string',
            'items.*.price' => 'required|integer|min:1',
            'items.*.qty'   => 'required|integer|min:1',
            'lang'          => 'sometimes|string|in:zh,en',
        ]);

        $items       = $request->input('items');
        $lang        = $request->input('lang', 'zh');
        $totalAmount = (int) array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $items));
        $itemName    = implode('#', array_map(fn($i) => $i['name'] . ' x' . $i['qty'], $items));

        // 綠界 ItemName 最多 200 字
        if (mb_strlen($itemName) > 200) {
            $itemName = mb_substr($itemName, 0, 197) . '...';
        }

        // 訂單編號：DEMO + 時間戳 + 亂數（唯一不重複）
        $tradeNo   = 'DEMO' . date('YmdHis') . rand(10, 99);
        $tradeDate = date('Y/m/d H:i:s');

        $backendUrl  = rtrim(config('app.url'), '/');
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        $params = [
            'MerchantID'        => $this->merchantId,
            'MerchantTradeNo'   => $tradeNo,
            'MerchantTradeDate' => $tradeDate,
            'PaymentType'       => 'aio',
            'TotalAmount'       => $totalAmount,
            'TradeDesc'         => 'GreenShop Demo Order',
            'ItemName'          => $itemName,
            // ReturnURL: 綠界伺服器通知（本地開發時無法到達，正式部署才會通）
            'ReturnURL'         => $backendUrl . '/api/demo/payment-return',
            // OrderResultURL: 瀏覽器付款後 POST 到這裡，再轉到前端結果頁
            'OrderResultURL'    => $backendUrl . '/api/demo/payment-result?lang=' . $lang,
            'ChoosePayment'     => 'Credit',
            'EncryptType'       => 1,
        ];

        $params['CheckMacValue'] = $this->generateCheckMac($params);

        return response()->json([
            'url'    => $this->paymentUrl,
            'params' => $params,
        ]);
    }

    /**
     * 綠界伺服器 → 後端通知（Server to Server）
     * 本地開發時綠界打不到，正式部署才有效
     */
    public function paymentReturn(Request $request)
    {
        // TODO（正式上線）：驗證 CheckMacValue，更新訂單狀態
        return response('1|OK', 200)->header('Content-Type', 'text/plain');
    }

    /**
     * 綠界付款完成後 → 瀏覽器 POST 到這裡 → 轉跳前端結果頁
     */
    public function paymentResult(Request $request)
    {
        $rtnCode  = $request->input('RtnCode', 0);
        $rtnMsg   = $request->input('RtnMsg', '');
        $tradeNo  = $request->input('MerchantTradeNo', '');
        $amount   = $request->input('TradeAmt', 0);
        $lang     = $request->query('lang', 'zh');

        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $status      = ($rtnCode == 1) ? 'success' : 'failed';

        return redirect(
            $frontendUrl . '/' . $lang . '/demo/payment-result'
            . '?status='  . $status
            . '&msg='     . urlencode($rtnMsg)
            . '&no='      . urlencode($tradeNo)
            . '&amount='  . $amount
        );
    }

    /**
     * 產生綠界 CheckMacValue（SHA256）
     */
    private function generateCheckMac(array $params): string
    {
        ksort($params);

        $str = 'HashKey=' . $this->hashKey . '&';
        foreach ($params as $key => $value) {
            $str .= $key . '=' . $value . '&';
        }
        $str .= 'HashIV=' . $this->hashIV;

        // URL encode → 小寫 → 還原綠界指定字元
        $str = strtolower(urlencode($str));
        $str = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
            ['-',   '_',   '.',   '!',   '*',   '(',   ')'],
            $str
        );

        return strtoupper(hash('sha256', $str));
    }
}
