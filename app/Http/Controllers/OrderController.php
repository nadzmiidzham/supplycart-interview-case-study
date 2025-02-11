<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

use App\Models\User;
use App\Models\Order;

use App\Helpers\UserLogger;

class OrderController extends Controller {
    /**
     * Display the user's order.
     */
    public function view(Request $request): Response
    {
        $user = $request->user();
        $orderList = DB::table('orders')
            ->select('orders.id', 'orders.total_price', 'orders.created_at')
            ->leftJoin('users', 'users.id', '=', 'orders.user_id')
            ->where('users.id', $user->id)
            ->get();

        return Inertia::render('Order/View', [
            'orderList' => $orderList->map(fn ($order) => [
                'orderId' => (int) $order->id,
                'totalPrice' => (float) $order->total_price,
                'createdAt' => $order->created_at
            ]),
        ]);
    }

    /**
     * Display the user's order.
     */
    public function detail(Request $request, int $id): Response
    {
        $order = DB::table('orders')
            ->select(
                'orders.id', 'orders.total_price', 'orders.created_at',
            )
            ->where('orders.id', $id)
            ->first();
        $productList = DB::table('products')
            ->select(
                'products.id', 'products.name', 'products.price',
                'product_brands.name as brandName', 'product_categories.name as categoryName'
            )
            ->leftJoin('product_brands', 'product_brands.id', '=', 'products.brand_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.id', $id)
            ->get();

        return Inertia::render('Order/Detail', [
            'order' => [
                'orderId' => (int) $order->id,
                'totalPrice' => (float) $order->total_price,
                'createdAt' => $order->created_at
            ],
            'productList' => $productList->map(fn ($product) => [
                'productId' => (int) $product->id,
                'productName' => $product->name,
                'productPrice' => (float) $product->price,
                'brandName' => $product->brandName,
                'categoryName' => $product->categoryName,
            ]),
        ]);
    }

    /**
     * Add item to cart.
     */
    public function checkout(Request $request)
    {
        $user = $request->user();

        $cart = DB::table('carts')
            ->select('carts.id')
            ->leftJoin('users', 'users.id', '=', 'carts.user_id')
            ->where('users.id', $user->id)
            ->first();

        $orderItemList = DB::table('cart_items')
            ->select(
                'products.id as productId', 'products.price',
                'cart_items.quantity'
            )
            ->leftJoin('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->leftJoin('products', 'products.id', '=', 'cart_items.product_id')
            ->where('carts.id', $cart->id)
            ->where('cart_items.is_active', true)
            ->get();

        // create new order
        $orderTotalPrice = $orderItemList->reduce(
            fn ($sum, $item) => ($sum += $item->price * $item->quantity)
        );
        $newOrderId = DB::table('orders')
            ->insertGetId([
                'user_id' => $user->id,
                'total_price' => $orderTotalPrice,
                'created_at' => now(),
            ]);

        DB::table('order_items')
            ->insert($orderItemList->map(fn ($item) => [
                'order_id' => $newOrderId,
                'product_id' => $item->productId,
                'quantity' => $item->quantity,
            ])->toArray());

        // clean current cart
        DB::table('cart_items')
            ->leftJoin('carts', 'carts.id', '=', 'cart_items.cart_id')
            ->leftJoin('users', 'users.id', '=', 'carts.user_id')
            ->where('users.id', $user->id)
            ->where('carts.id', $cart->id)
            ->update([
                'is_active' => false,
            ]);

        // set log
        UserLogger::checkout($user->id, [
            'orderId' => $newOrderId,
            'orderItemList' => $orderItemList
        ]);

        return Inertia::location(route('order.view'));
    }
}
