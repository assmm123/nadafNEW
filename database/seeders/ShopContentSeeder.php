<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CommunicationMethod;
use App\Models\Page;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Slide;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShopContentSeeder extends Seeder
{
    public function run(): void
    {
        // ===== المستخدمون =====
        // دور المالك: owner (القيمة القديمة admin تُترجم تلقائيًا إلى owner)
        User::create([
            'name' => 'مدير المتجر',
            'email' => 'admin@nadaf.store',
            'phone' => '+963 999 123 456',
            'password' => Hash::make('admin123'),
            'role' => \App\Support\StaffPermissions::ROLE_OWNER,
            'status' => 'active',
        ]);

        User::create([
            'name' => 'عميل تجريبي',
            'email' => 'customer@nadaf.store',
            'phone' => '+963 931 111 222',
            'password' => Hash::make('customer123'),
            'role' => 'customer',
            'status' => 'active',
        ]);

        // ===== الأقسام =====
        $ties = Category::create(['name_ar' => 'كرافات', 'name_en' => 'Ties', 'slug' => 'ties', 'image' => 'images/placeholders/cat-ties.svg', 'sort_order' => 1]);
        $sets = Category::create(['name_ar' => 'أطقم رسمية', 'name_en' => 'Formal Sets', 'slug' => 'formal-sets', 'image' => 'images/placeholders/cat-sets.svg', 'sort_order' => 2]);
        $acc = Category::create(['name_ar' => 'إكسسوارات', 'name_en' => 'Accessories', 'slug' => 'accessories', 'image' => 'images/placeholders/cat-accessories.svg', 'sort_order' => 3]);

        // ===== المنتجات =====
        $products = [
            [
                'category' => $ties, 'name_ar' => 'كرافات كلاسيك حرير كحلي', 'name_en' => 'Classic Silk Tie Navy',
                'slug' => 'classic-silk-tie-navy', 'price' => 12, 'old' => 16, 'wholesale' => 9,
                'featured' => true, 'desc_ar' => 'كرافات كلاسيكية من الحرير الطبيعي بلون كحلي داكن، مثالية للمناسبات الرسمية والعمل. عرض 8 سم بطول 145 سم.',
                'desc_en' => 'Classic tie in natural silk, dark navy color — perfect for formal occasions and work. 8cm width, 145cm length.',
                'colors' => ['كحلي', 'أسود', 'بورجوندي'], 'sizes' => [], 'img' => 'images/placeholders/p-tie-1.svg', 'qty' => 15,
            ],
            [
                'category' => $ties, 'name_ar' => 'كرافات مخططة أنيقة', 'name_en' => 'Elegant Striped Tie',
                'slug' => 'elegant-striped-tie', 'price' => 10, 'old' => null, 'wholesale' => null,
                'featured' => true, 'desc_ar' => 'كرافات بخطوط متقاطعة أنيقة، خامة بريميوم لا تجعد وسهلة الكي.',
                'desc_en' => 'Tie with elegant cross stripes, premium wrinkle-resistant fabric.',
                'colors' => ['رمادي', 'أزرق'], 'sizes' => [], 'img' => 'images/placeholders/p-tie-2.svg', 'qty' => 20,
            ],
            [
                'category' => $ties, 'name_ar' => 'بديلة بابيون سوداء', 'name_en' => 'Black Bow Tie',
                'slug' => 'black-bow-tie', 'price' => 8, 'old' => null, 'wholesale' => 6,
                'featured' => false, 'desc_ar' => 'بديلة (بابيون) سوداء جاهزة الارتداء، مناسبة للسويت والمناسبات الرسمية.',
                'desc_en' => 'Pre-tied black bow tie, perfect for tuxedos and formal events.',
                'colors' => [], 'sizes' => [], 'img' => 'images/placeholders/p-bow.svg', 'qty' => 0, // بلا متغيرات = دائمًا متوفر
            ],
            [
                'category' => $sets, 'name_ar' => 'طقم رسمي كرافاة وبديلة ومنديل', 'name_en' => 'Formal Set: Tie, Bow & Pocket Square',
                'slug' => 'formal-gift-set', 'price' => 22, 'old' => 28, 'wholesale' => 18,
                'featured' => true, 'desc_ar' => 'طقم هدايا فاخر يشمل كرافاة وبديلة ومنديل جيب بعلبة أنيقة — هدية مثالية.',
                'desc_en' => 'Luxury gift set including a tie, bow tie and pocket square in an elegant box — a perfect gift.',
                'colors' => ['كحلي/ذهبي', 'أسود/فضي', 'بورجوندي'], 'sizes' => [], 'img' => 'images/placeholders/p-set-1.svg', 'qty' => 8,
            ],
            [
                'category' => $sets, 'name_ar' => 'طقم حزام ومحفظة جلد', 'name_en' => 'Leather Belt & Wallet Set',
                'slug' => 'leather-belt-wallet-set', 'price' => 25, 'old' => null, 'wholesale' => 20,
                'featured' => false, 'desc_ar' => 'طقم من الجلد الطبيعي يشمل حزام ومحفظة رجالية بعلبة هدية.',
                'desc_en' => 'Genuine leather set including a belt and men\'s wallet in a gift box.',
                'colors' => ['بني', 'أسود'], 'sizes' => ['M', 'L', 'XL'], 'img' => 'images/placeholders/p-set-2.svg', 'qty' => 6,
            ],
            [
                'category' => $acc, 'name_ar' => 'مشبك كرافاة معدني', 'name_en' => 'Metal Tie Clip',
                'slug' => 'metal-tie-clip', 'price' => 5, 'old' => 7, 'wholesale' => 3.5,
                'featured' => true, 'desc_ar' => 'مشبك كرافاة معدني بطلاء ذهبي يضيف لمسة راقية لملابسك.',
                'desc_en' => 'Gold-plated metal tie clip for an elegant touch.',
                'colors' => [], 'sizes' => [], 'img' => 'images/placeholders/p-clip.svg', 'qty' => 0,
            ],
            [
                'category' => $acc, 'name_ar' => 'ساعة يد كلاسيكية بسوار جلد', 'name_en' => 'Classic Leather Watch',
                'slug' => 'classic-leather-watch', 'price' => 35, 'old' => 45, 'wholesale' => 30,
                'featured' => false, 'desc_ar' => 'ساعة يد كلاسيكية بميناء بسيط وسوار جلد طبيعي، مقاومة للماء.',
                'desc_en' => 'Classic watch with a clean dial and genuine leather strap, water resistant.',
                'colors' => ['بني', 'أسود'], 'sizes' => [], 'img' => 'images/placeholders/p-watch.svg', 'qty' => 5,
            ],
            [
                'category' => $acc, 'name_ar' => 'أزرار أكمام ذهبية', 'name_en' => 'Gold Cufflinks',
                'slug' => 'gold-cufflinks', 'price' => 9, 'old' => null, 'wholesale' => 7,
                'featured' => false, 'desc_ar' => 'أزرار أكمام بطلاء ذهبي بتصميم كلاسيكي، تأتي بعلبة أنيقة.',
                'desc_en' => 'Gold-plated cufflinks with a classic design, in an elegant box.',
                'colors' => [], 'sizes' => [], 'img' => 'images/placeholders/p-cufflinks.svg', 'qty' => 0,
            ],
        ];

        foreach ($products as $p) {
            $product = Product::create([
                'category_id' => $p['category']->id,
                'name_ar' => $p['name_ar'],
                'name_en' => $p['name_en'],
                'slug' => $p['slug'],
                'description_ar' => $p['desc_ar'],
                'description_en' => $p['desc_en'],
                'price_usd' => $p['price'],
                'wholesale_price_usd' => $p['wholesale'],
                'old_price_usd' => $p['old'],
                'is_featured' => $p['featured'],
                'allow_inquiry' => true,
                'is_active' => true,
            ]);

            ProductMedia::create([
                'product_id' => $product->id,
                'file_path' => $p['img'],
                'is_main' => true,
                'sort_order' => 0,
            ]);

            // متغيرات: لون × مقاس (أو متغير واحد إن لم يوجد لون)
            $colors = $p['colors'] ?: [null];
            $sizes = $p['sizes'] ?: [null];
            if ($p['qty'] === 0) {
                $colors = [null];
                $sizes = [null];
            }
            foreach ($colors as $ci => $color) {
                foreach ($sizes as $si => $size) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'color' => $color,
                        'size' => $size,
                        'quantity' => max(1, $p['qty'] - $ci - $si),
                    ]);
                }
            }
        }

        // ===== وسائل الدفع =====
        PaymentMethod::create(['type' => 'sham_cash', 'name' => 'شام كاش', 'account_number' => '0999 123 456', 'account_name' => 'متجر نداف', 'instructions' => 'حوّل المبلغ إلى الرقم أعلاه عبر تطبيق شام كاش، ثم ارفع صورة الحوالة هنا مع كود الحوالة.', 'is_active' => true, 'is_default' => true, 'requires_proof' => true, 'sort_order' => 1]);
        PaymentMethod::create(['type' => 'bank', 'name' => 'حوالة بنكية', 'account_number' => 'XXXX-XXXX-1234', 'account_name' => 'متجر نداف — بنك سوريا الدولي', 'instructions' => 'حوّل المبلغ إلى الحساب البنكي ثم ارفع إشعار الحوالة هنا.', 'is_active' => true, 'is_default' => false, 'requires_proof' => true, 'sort_order' => 2]);
        PaymentMethod::create(['type' => 'cash_on_delivery', 'name' => 'الدفع عند الاستلام', 'account_number' => null, 'account_name' => null, 'instructions' => 'ادفع نقدًا عند استلام الطلب (متاح للتوصيل المحلي فقط).', 'is_active' => true, 'is_default' => false, 'requires_proof' => false, 'sort_order' => 3]);

        // ===== وسائل التواصل =====
        CommunicationMethod::create(['type' => 'whatsapp', 'label' => 'واتساب المتجر', 'value' => '+963999123456', 'sort_order' => 1]);
        CommunicationMethod::create(['type' => 'telegram', 'label' => 'تيليجرام', 'value' => 'nadaf_store', 'sort_order' => 2]);
        CommunicationMethod::create(['type' => 'email', 'label' => 'البريد الإلكتروني', 'value' => 'info@nadaf.store', 'sort_order' => 3]);
        CommunicationMethod::create(['type' => 'phone', 'label' => 'الهاتف', 'value' => '+963 999 123 456', 'sort_order' => 4]);

        // ===== الصفحات الثابتة =====
        Page::create(['slug' => 'about', 'title_ar' => 'من نحن', 'title_en' => 'About Us', 'content_ar' => "متجر **نداف** متخصص في بيع الكرافات والأطقم الرسمية والإكسسوارات الرجالية بجودة عالية وأسعار منافسة.\n\nنؤمن بأن الأناقة تبدأ من التفاصيل الصغيرة، لذا نختار كل منتج بعناية فائقة ليمنحك حضورًا يليق بك.\n\n- منتجات أصلية وضمانة\n- توصيل سريع داخل المدينة\n- عروض خاصة للجملة", 'content_en' => "**NADAF** store specializes in ties, formal sets and men's accessories with premium quality and competitive prices.\n\nWe believe elegance starts with the details, so every product is hand-picked to give you the presence you deserve.\n\n- Original guaranteed products\n- Fast local delivery\n- Special wholesale offers"]);

        Page::create(['slug' => 'exchange-policy', 'title_ar' => 'سياسة الاستبدال والإرجاع', 'title_en' => 'Exchange & Return Policy', 'content_ar' => "1. يمكن استبدال المنتج خلال 7 أيام من الاستلام بشرط عدم الاستخدام وبحالته الأصلية.\n2. لا يشمل الإرجاع المنتجات المخصصة أو المخصومة بأكثر من 50%.\n3. تُرد أجرة التوصيل في حال وجود عيب مصنعي بالمنتج.\n4. للحصول على استبدال، تواصل معنا عبر وسائل التواصل مع ذكر كود الطلب.", 'content_en' => "1. Products can be exchanged within 7 days of receipt if unused and in original condition.\n2. Returns do not apply to customized items or items discounted over 50%.\n3. Delivery fees are refunded for manufacturing defects.\n4. To request an exchange, contact us with your order code."]);

        Page::create(['slug' => 'faq', 'title_ar' => 'الأسئلة الشائعة', 'title_en' => 'FAQ', 'content_ar' => "**كيف أتابع طلبي؟**\nاستخدم كود الطلب الذي وصلك بعد التأكيد من صفحة \"طلباتي\" في حسابك.\n\n**هل التسجيل إلزامي للطلب؟**\nنعم، يتيح لنا التسجيل متابعة طلباتك وحالة كل طلب.\n\n**كيف تُحسب أسعار الليرة؟**\nتحسب الليرة تلقائيًا بسعر الصرف المحدد من المتجر لحظة الطلب.\n\n**هل توجد أسعار جملة؟**\nنعم، تطبق أسعار الجملة تلقائيًا عند بلوغ الحد الأدنى للكمية أو القيمة.", 'content_en' => "**How do I track my order?**\nUse the order code sent after checkout from the \"My Orders\" page.\n\n**Is registration required to order?**\nYes, it lets us track your orders and their status.\n\n**How are SYP prices calculated?**\nSYP prices are computed automatically using the store exchange rate at order time.\n\n**Do you offer wholesale?**\nYes, wholesale prices apply automatically when reaching the minimum quantity or amount."]);

        // ===== السلايدر =====
        Slide::create(['title_ar' => 'أناقة تليق بك', 'title_en' => 'Elegance You Deserve', 'image_path' => 'images/placeholders/slide-1.svg', 'sort_order' => 1]);
        Slide::create(['title_ar' => 'مجموعة الكرافات الجديدة', 'title_en' => 'New Tie Collection', 'image_path' => 'images/placeholders/slide-2.svg', 'link' => '/c/ties', 'sort_order' => 2]);
    }
}
