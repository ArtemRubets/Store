<?php


namespace App\Repositories;


use App\Interfaces\IProductRepositoryInterface;
use App\Models\Product;

class ProductRepository extends CoreRepository implements IProductRepositoryInterface
{
    public function getModel(){
        return Product::class;
    }

    public function getCategoryProducts($category){

        $categoryProducts = $this->startCondition()->with('price')->where('category_id', $category->id)
            ->orderBy('created_at')
            ->orderBy('product_count', 'desc')->get();

        foreach ($categoryProducts as $product){

            $price = $this->getPrice($product);
            $product->product_price = $price;

            if ($product->is_offer){
                $priceWithOffer = round($product->product_price - ( $product->product_price * $product->offer_percent / 100 ), 2);

                $product->product_price_with_offer = $priceWithOffer;
            }
        }

        return $categoryProducts;
    }

    public function getCategoryProductsForDashboard($category){

        $categoryProducts = $this->startCondition()->where('category_id', $category->id)
            ->orderBy('created_at')
            ->orderBy('product_count', 'desc')->get();

        return $categoryProducts;
    }

    public function getProduct($product_slug){
        $product = $this->startCondition()->where('product_slug' , $product_slug)
            ->firstOrFail();

        $price = $this->getPrice($product);

        $product->product_price = $price;

        if ($product->is_offer){
            $priceWithOffer = round($product->product_price - ( $product->product_price * $product->offer_percent / 100 ), 2);

            $product->product_price_with_offer = $priceWithOffer;
        }
        return $product;
    }

    public function getProductForDashboard($id){
        $product = $this->startCondition()->findOrFail($id);
        if ($product->is_offer){
            $priceWithOffer = round($product->product_price - ( $product->product_price * $product->offer_percent / 100 ), 2);

            $product->product_price_with_offer = $priceWithOffer;
        }
        return $product;
    }

    public function find($id){
        return $this->startCondition()->findOrFail($id);
    }

    public function getTrashedProducts(){
        return $this->startCondition()->onlyTrashed()->get();
    }

    public function productRestore($id){
        return $this->startCondition()->withTrashed()->where('id', $id)->restore();
    }

    public function productUpdate($product, $validatedInputs)
    {
        if ($validatedInputs){

            //TODO Cut element from array
            $product_prices = $validatedInputs['product_prices'];
            unset($validatedInputs['product_prices']);

            //TODO Doesn't work in observer. Why?
            if (!isset($validatedInputs['is_offer'])) $validatedInputs['is_offer'] = false;


            foreach ($product_prices as $currencyId => $product_price){

                foreach ($product->price as $productPriceModel) {

                    if ($productPriceModel->currency_id == $currencyId){

                        $productPriceModel->price = $product_price;
                        $save = $productPriceModel->save();
                    }
                }

            }

        }
        return ($product->update($validatedInputs) && $save) === true ? true : false;
    }

    public function productStore($validatedInputs)
    {
        if ($validatedInputs) {

            //TODO Cut element from array
            $product_prices = $validatedInputs['product_prices'];
            unset($validatedInputs['product_prices']);

            if (!isset($validatedInputs['is_offer'])) $validatedInputs['is_offer'] = false;

            $storeProduct = $this->startCondition()->create($validatedInputs);

            foreach ($product_prices as $currencyId => $product_price){

                $storeProduct->price()->attach([$storeProduct->id], ['currency_id' => $currencyId, 'price' => $product_price]);

            }

            return $storeProduct ?? true;
        }

    }

    public function forceDelete($id){
        return $this->startCondition()->withTrashed()->where('id', $id)->forceDelete();
    }

    public function getPrice($product)
    {
        $price = $product->price;

       return $price->where('currency_id', session('currency')->id)->first()->price;
    }
}
