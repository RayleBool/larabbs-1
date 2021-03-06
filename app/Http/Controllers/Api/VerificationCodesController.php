<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Overtrue\EasySms\EasySms;
use App\Http\Requests\Api\VerificationCodeRequest;

class VerificationCodesController extends Controller
{
    public function store(VerificationCodeRequest $request, EasySms $easySms)
    {
        $captchaData = \Cache::get($request->captcha_key);

        if (!$captchaData) {
            return $this->response->error('图片验证码已失效', 422);
        }

        if (!hash_equals($captchaData['code'], $request->captcha_code)) {
            \Cache::forget($request->captcha_key);
            return $this->response->errorUnauthorized('验证码错误');
        }

        $phone = $captchaData['phone'];

        $code = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);

        if (!app()->environment('production')) {
            $code = '1234';
        } else {
            try {
                $result = $easySms->send($phone, [
                    'content' => '您已成功预约',
                    'template' => 'SMS_121400007',
                    'data' => [
                        'hospital' => '武威医院',
                        'date' => date('Y-m-d H:i:s' ,time()),
                        'orderid' => 1,
                    ],
                ]);
            } catch (\GuzzleHttp\Exception\ClientException $exception) {
                $response = $exception->getResponse();
                $result = json_decode($response->getBody()->getContents(), true);
                return $this->response->errorInternal($result['msg'] ?? '短信发送异常');
            }
        }

        $key = 'verificationCode_'.str_random(15);
        $expiredAt = now()->addMinutes(15);

        \Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);

        \Cache::forget($request->captcha_key);

        return $this->response->array([
                'key' => $key,
                'expired_at' => $expiredAt->toDateTimeString(),
            ])->setStatusCode(201);
    }
}
