<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Util\Helper;
use Illuminate\Http\Request;
use App\Models\Cupom;
use App\Http\Util\Payments\MercadoPago;

class VendasController extends Controller
{
    //update server
    private $apiMercadoPago;


    public function __construct()
    {
        $this->apiMercadoPago = new MercadoPago();
    }

    public function index() : object
    {

        dd($this->apiMercadoPago->paymentPreference());
    }


}
