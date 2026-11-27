<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class SembakoCatalogSeeder extends Seeder
{
    /**
     * Seed 180 additional realistic Indonesian toko sembako products (PRD-021 .. PRD-200)
     * to bring the catalog total to 200 products idempotently.
     */
    public function run(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMINISTRATOR)->first();

        $categories = [
            'Instant Noodles' => Category::firstOrCreate(['name' => 'Instant Noodles'], ['is_active' => true]),
            'Beverages' => Category::firstOrCreate(['name' => 'Beverages'], ['is_active' => true]),
            'Basic Goods' => Category::firstOrCreate(['name' => 'Basic Goods'], ['is_active' => true]),
            'Snacks' => Category::firstOrCreate(['name' => 'Snacks'], ['is_active' => true]),
            'Household Products' => Category::firstOrCreate(['name' => 'Household Products'], ['is_active' => true]),
        ];

        $items = [
            // Instant Noodles (20 items: PRD-021 .. PRD-040)
            ['PRD-021', 'Indomie Ayam Bawang', 'Instant Noodles', 3200, 55, 15, 'pcs'],
            ['PRD-022', 'Indomie Kari Ayam', 'Instant Noodles', 3400, 48, 15, 'pcs'],
            ['PRD-023', 'Indomie Goreng Rendang', 'Instant Noodles', 3500, 36, 12, 'pcs'],
            ['PRD-024', 'Indomie Goreng Aceh', 'Instant Noodles', 3500, 28, 12, 'pcs'],
            ['PRD-025', 'Indomie Goreng Jumbo', 'Instant Noodles', 4500, 30, 10, 'pcs'],
            ['PRD-026', 'Indomie Hype Abis Seblak', 'Instant Noodles', 3500, 22, 10, 'pcs'],
            ['PRD-027', 'Mie Sedaap Goreng', 'Instant Noodles', 3300, 60, 20, 'pcs'],
            ['PRD-028', 'Mie Sedaap Soto', 'Instant Noodles', 3300, 45, 15, 'pcs'],
            ['PRD-029', 'Mie Sedaap Kari Spesial', 'Instant Noodles', 3400, 32, 12, 'pcs'],
            ['PRD-030', 'Mie Sedaap Korean Spicy', 'Instant Noodles', 3800, 25, 10, 'pcs'],
            ['PRD-031', 'Sarimi Isi 2 Ayam Kecap', 'Instant Noodles', 4200, 30, 10, 'pcs'],
            ['PRD-032', 'Sarimi Isi 2 Baso Sapi', 'Instant Noodles', 4200, 26, 10, 'pcs'],
            ['PRD-033', 'Supermi Ayam Bawang', 'Instant Noodles', 3200, 24, 10, 'pcs'],
            ['PRD-034', 'Pop Mie Ayam', 'Instant Noodles', 5500, 35, 12, 'pcs'],
            ['PRD-035', 'Pop Mie Baso', 'Instant Noodles', 5500, 32, 12, 'pcs'],
            ['PRD-036', 'Pop Mie Kari Ayam', 'Instant Noodles', 5500, 20, 10, 'pcs'],
            ['PRD-037', 'Pop Mie Goreng', 'Instant Noodles', 5500, 18, 10, 'pcs'],
            ['PRD-038', 'Lemonilo Mie Goreng', 'Instant Noodles', 7500, 16, 6, 'pcs'],
            ['PRD-039', 'Indomie Goreng 1 Dus (40 pcs)', 'Instant Noodles', 124000, 8, 3, 'box'],
            ['PRD-040', 'Mie Burung Dara Pipih 140g', 'Instant Noodles', 5500, 25, 8, 'pack'],

            // Basic Goods & Bumbu Dapur (55 items: PRD-041 .. PRD-095)
            ['PRD-041', 'Beras Pandan Wangi 5kg', 'Basic Goods', 82000, 10, 4, 'pack'],
            ['PRD-042', 'Beras Ramos Setra 10kg', 'Basic Goods', 148000, 6, 3, 'pack'],
            ['PRD-043', 'Beras Eceran 1kg', 'Basic Goods', 15000, 35, 10, 'kg'],
            ['PRD-044', 'Minyak Goreng Bimoli 2L', 'Basic Goods', 38500, 18, 6, 'liter'],
            ['PRD-045', 'Minyak Goreng SunCo 2L', 'Basic Goods', 39000, 14, 6, 'liter'],
            ['PRD-046', 'Minyak Goreng Tropical 1L', 'Basic Goods', 19500, 20, 8, 'liter'],
            ['PRD-047', 'Minyak Goreng Minyakita 1L', 'Basic Goods', 16000, 25, 10, 'liter'],
            ['PRD-048', 'Gula Pasir Gulaku 1kg', 'Basic Goods', 18500, 24, 8, 'kg'],
            ['PRD-049', 'Gula Merah / Gula Jawa 500g', 'Basic Goods', 14000, 15, 5, 'pack'],
            ['PRD-050', 'Tepung Terigu Segitiga Biru 1kg', 'Basic Goods', 14000, 22, 8, 'kg'],
            ['PRD-051', 'Tepung Terigu Cakra Kembar 1kg', 'Basic Goods', 15000, 14, 6, 'kg'],
            ['PRD-052', 'Tepung Beras Rose Brand 500g', 'Basic Goods', 9000, 20, 6, 'pack'],
            ['PRD-053', 'Tepung Ketan Rose Brand 500g', 'Basic Goods', 11000, 15, 5, 'pack'],
            ['PRD-054', 'Tepung Tapioka Pak Tani 500g', 'Basic Goods', 9500, 18, 6, 'pack'],
            ['PRD-055', 'Tepung Bumbu Sajiku 220g', 'Basic Goods', 6500, 28, 10, 'pack'],
            ['PRD-056', 'Tepung Maizena Maizenaku 150g', 'Basic Goods', 6000, 16, 6, 'box'],
            ['PRD-057', 'Telur Ayam Negeri 1/2 kg', 'Basic Goods', 15000, 18, 6, 'pack'],
            ['PRD-058', 'Telur Puyuh 250g', 'Basic Goods', 11000, 10, 4, 'pack'],
            ['PRD-059', 'Garam Refina 250g', 'Basic Goods', 3500, 35, 10, 'pack'],
            ['PRD-060', 'Kecap Bango 520ml', 'Basic Goods', 24500, 16, 6, 'pack'],
            ['PRD-061', 'Kecap Bango Sachet 20ml', 'Basic Goods', 2000, 50, 15, 'pcs'],
            ['PRD-062', 'Kecap ABC Sedang 275ml', 'Basic Goods', 15500, 14, 5, 'bottle'],
            ['PRD-063', 'Saus Sambal ABC 335ml', 'Basic Goods', 17500, 18, 6, 'bottle'],
            ['PRD-064', 'Saus Tomat ABC 335ml', 'Basic Goods', 16500, 12, 5, 'bottle'],
            ['PRD-065', 'Saus Sambal Sachet Indofood', 'Basic Goods', 1000, 90, 25, 'pcs'],
            ['PRD-066', 'Royco Kaldu Ayam Sachet', 'Basic Goods', 500, 120, 30, 'pcs'],
            ['PRD-067', 'Royco Kaldu Sapi Sachet', 'Basic Goods', 500, 100, 30, 'pcs'],
            ['PRD-068', 'Masako Ayam Sachet', 'Basic Goods', 500, 110, 30, 'pcs'],
            ['PRD-069', 'Sasa Micin / MSG 250g', 'Basic Goods', 11500, 20, 6, 'pack'],
            ['PRD-070', 'Ladaku Merica Bubuk Sachet', 'Basic Goods', 1500, 60, 20, 'pcs'],
            ['PRD-071', 'Desaku Ketumbar Bubuk Sachet', 'Basic Goods', 1500, 48, 15, 'pcs'],
            ['PRD-072', 'Desaku Kunyit Bubuk Sachet', 'Basic Goods', 1500, 40, 15, 'pcs'],
            ['PRD-073', 'Bumbu Racik Nasi Goreng', 'Basic Goods', 2500, 45, 15, 'pcs'],
            ['PRD-074', 'Bumbu Racik Sayur Sop', 'Basic Goods', 2500, 40, 15, 'pcs'],
            ['PRD-075', 'Bumbu Racik Tempe Goreng', 'Basic Goods', 2500, 50, 15, 'pcs'],
            ['PRD-076', 'Bumbu Racik Ayam Goreng', 'Basic Goods', 2500, 42, 15, 'pcs'],
            ['PRD-077', 'Santan Kara Segitiga 65ml', 'Basic Goods', 4000, 55, 15, 'pcs'],
            ['PRD-078', 'Santan Kara Kotak 200ml', 'Basic Goods', 10500, 20, 8, 'box'],
            ['PRD-079', 'Margarin Blue Band Sachet 200g', 'Basic Goods', 9500, 24, 8, 'pack'],
            ['PRD-080', 'Margarin Forvita 200g', 'Basic Goods', 7500, 18, 6, 'pack'],
            ['PRD-081', 'Terasi ABC Sachet', 'Basic Goods', 1000, 75, 20, 'pcs'],
            ['PRD-082', 'Bihun Jagung Padamu 320g', 'Basic Goods', 7500, 22, 8, 'pack'],
            ['PRD-083', 'Soun Naga 100g', 'Basic Goods', 5500, 20, 6, 'pack'],
            ['PRD-084', 'Sarden ABC Tomat 155g', 'Basic Goods', 11500, 24, 8, 'pcs'],
            ['PRD-085', 'Sarden ABC Cabai 425g', 'Basic Goods', 24500, 14, 5, 'pcs'],
            ['PRD-086', 'Kornet Sapi Pronas 198g', 'Basic Goods', 23500, 10, 4, 'pcs'],
            ['PRD-087', 'Keju Kraft Cheddar 160g', 'Basic Goods', 21000, 14, 5, 'box'],
            ['PRD-088', 'Selai Coklat Ceres 200g', 'Basic Goods', 22000, 10, 4, 'pcs'],
            ['PRD-089', 'Coklat Meises Ceres 90g', 'Basic Goods', 11000, 16, 6, 'pack'],
            ['PRD-090', 'Agar-Agar Swallow Globe', 'Basic Goods', 5000, 30, 10, 'pcs'],
            ['PRD-091', 'Nutrijell Coklat 20g', 'Basic Goods', 5500, 26, 8, 'pcs'],
            ['PRD-092', 'Cuka Makan Dixi 150ml', 'Basic Goods', 4500, 18, 6, 'bottle'],
            ['PRD-093', 'Minyak Wijen Oh Guan Hing 150ml', 'Basic Goods', 24000, 8, 3, 'bottle'],
            ['PRD-094', 'Kacang Tanah Kupas 500g', 'Basic Goods', 18000, 14, 5, 'pack'],
            ['PRD-095', 'Kacang Hijau 500g', 'Basic Goods', 15000, 16, 5, 'pack'],

            // Beverages (40 items: PRD-096 .. PRD-135)
            ['PRD-096', 'Aqua 330ml', 'Beverages', 3000, 48, 15, 'bottle'],
            ['PRD-097', 'Aqua 1500ml', 'Beverages', 7000, 30, 10, 'bottle'],
            ['PRD-098', 'Le Minerale 600ml', 'Beverages', 4000, 45, 15, 'bottle'],
            ['PRD-099', 'Le Minerale 1500ml', 'Beverages', 7000, 28, 10, 'bottle'],
            ['PRD-100', 'Le Minerale Galon 15L', 'Beverages', 22000, 10, 4, 'bottle'],
            ['PRD-101', 'Teh Pucuk Harum 350ml', 'Beverages', 4000, 42, 15, 'bottle'],
            ['PRD-102', 'Teh Gelas Cup 180ml', 'Beverages', 1500, 60, 20, 'pcs'],
            ['PRD-103', 'Fruit Tea Apple 350ml', 'Beverages', 4500, 24, 8, 'bottle'],
            ['PRD-104', 'Frestea Green Tea 350ml', 'Beverages', 5000, 20, 8, 'bottle'],
            ['PRD-105', 'Floridina Orange 350ml', 'Beverages', 3500, 36, 12, 'bottle'],
            ['PRD-106', 'Minute Maid Pulpy Orange 300ml', 'Beverages', 6500, 18, 6, 'bottle'],
            ['PRD-107', 'Pocari Sweat 500ml', 'Beverages', 8000, 24, 8, 'bottle'],
            ['PRD-108', 'Mizone Cranberry 500ml', 'Beverages', 5500, 22, 8, 'bottle'],
            ['PRD-109', 'You C1000 Orange 140ml', 'Beverages', 7500, 20, 8, 'bottle'],
            ['PRD-110', 'Larutan Cap Kaki Tiga 320ml', 'Beverages', 7000, 24, 8, 'pcs'],
            ['PRD-111', 'Susu Bear Brand 189ml', 'Beverages', 10500, 30, 10, 'pcs'],
            ['PRD-112', 'Ultra Milk Coklat 250ml', 'Beverages', 7000, 28, 10, 'box'],
            ['PRD-113', 'Ultra Milk Full Cream 250ml', 'Beverages', 7000, 26, 10, 'box'],
            ['PRD-114', 'Ultra Milk Stroberi 250ml', 'Beverages', 7000, 20, 8, 'box'],
            ['PRD-115', 'Frisian Flag Kental Manis Pouch 545g', 'Beverages', 18000, 16, 6, 'pack'],
            ['PRD-116', 'Frisian Flag Kental Manis Sachet', 'Beverages', 2000, 60, 20, 'pcs'],
            ['PRD-117', 'Indomilk Coklat 190ml', 'Beverages', 5500, 24, 8, 'box'],
            ['PRD-118', 'Milo UHT 180ml', 'Beverages', 6500, 22, 8, 'box'],
            ['PRD-119', 'Dancow Fortigro Sachet 27g', 'Beverages', 4000, 45, 15, 'pcs'],
            ['PRD-120', 'Kapal Api Special 165g', 'Beverages', 18500, 16, 6, 'pack'],
            ['PRD-121', 'Kapal Api Special Mix Sachet', 'Beverages', 2000, 90, 25, 'pcs'],
            ['PRD-122', 'Good Day Cappuccino Sachet', 'Beverages', 2500, 70, 20, 'pcs'],
            ['PRD-123', 'Good Day Moccacino Sachet', 'Beverages', 2000, 65, 20, 'pcs'],
            ['PRD-124', 'Kopi ABC Susu Sachet', 'Beverages', 2000, 75, 20, 'pcs'],
            ['PRD-125', 'Luwak White Koffie Sachet', 'Beverages', 2000, 60, 20, 'pcs'],
            ['PRD-126', 'Torabika Creamy Latte Sachet', 'Beverages', 2500, 50, 15, 'pcs'],
            ['PRD-127', 'Nescafe Classic Sachet', 'Beverages', 1500, 55, 15, 'pcs'],
            ['PRD-128', 'Energen Coklat Sachet', 'Beverages', 2500, 48, 15, 'pcs'],
            ['PRD-129', 'Energen Kacang Hijau Sachet', 'Beverages', 2500, 40, 15, 'pcs'],
            ['PRD-130', 'Teh Sariwangi Celup (25 bag)', 'Beverages', 7500, 22, 8, 'box'],
            ['PRD-131', 'Teh Tong Tji Melati 40g', 'Beverages', 6000, 18, 6, 'pack'],
            ['PRD-132', 'Nutrisari Jeruk Peras Sachet', 'Beverages', 1500, 65, 20, 'pcs'],
            ['PRD-133', 'Pop Ice Coklat Sachet', 'Beverages', 1500, 50, 15, 'pcs'],
            ['PRD-134', 'Coca Cola 390ml', 'Beverages', 6000, 20, 8, 'bottle'],
            ['PRD-135', 'Sprite 390ml', 'Beverages', 6000, 18, 8, 'bottle'],

            // Snacks (30 items: PRD-136 .. PRD-165)
            ['PRD-136', 'Roma Biskuit Kelapa 300g', 'Snacks', 11500, 20, 6, 'pack'],
            ['PRD-137', 'Roma Malkist Crackers 135g', 'Snacks', 8500, 24, 8, 'pack'],
            ['PRD-138', 'Roma Sari Gandum 115g', 'Snacks', 9500, 18, 6, 'pack'],
            ['PRD-139', 'Oreo Original 119g', 'Snacks', 10500, 22, 8, 'pack'],
            ['PRD-140', 'Good Time Cookies 72g', 'Snacks', 8500, 20, 6, 'pack'],
            ['PRD-141', 'Beng-Beng Wafer 25g', 'Snacks', 2500, 60, 20, 'pcs'],
            ['PRD-142', 'Chocolatos Wafer Roll', 'Snacks', 1000, 80, 25, 'pcs'],
            ['PRD-143', 'Nabati Richeese Keju 50g', 'Snacks', 3000, 45, 15, 'pcs'],
            ['PRD-144', 'Nabati Richoco Coklat 50g', 'Snacks', 3000, 42, 15, 'pcs'],
            ['PRD-145', 'Tango Wafer Coklat 110g', 'Snacks', 8000, 18, 6, 'pack'],
            ['PRD-146', 'Better Sandwich Biscuit', 'Snacks', 2500, 40, 12, 'pcs'],
            ['PRD-147', 'Chitato Sapi Panggang 68g', 'Snacks', 11500, 22, 8, 'pack'],
            ['PRD-148', 'Qtela Singkong Balado 60g', 'Snacks', 7500, 24, 8, 'pack'],
            ['PRD-149', 'Kusuka Keripik Singkong 60g', 'Snacks', 7000, 20, 6, 'pack'],
            ['PRD-150', 'Taro Net Seaweed 32g', 'Snacks', 5500, 28, 10, 'pack'],
            ['PRD-151', 'Kacang Garuda Kulit 180g', 'Snacks', 15500, 16, 6, 'pack'],
            ['PRD-152', 'Kacang Atom Garuda 130g', 'Snacks', 11000, 18, 6, 'pack'],
            ['PRD-153', 'Kacang Dua Kelinci Sukro 100g', 'Snacks', 9500, 20, 6, 'pack'],
            ['PRD-154', 'Pilus Garuda Pedas 95g', 'Snacks', 8000, 22, 8, 'pack'],
            ['PRD-155', 'Sari Roti Tawar Kupas', 'Snacks', 19000, 10, 4, 'pack'],
            ['PRD-156', 'Sari Roti Sobek Coklat Keju', 'Snacks', 17500, 8, 4, 'pack'],
            ['PRD-157', 'Sari Roti Sandwich Coklat', 'Snacks', 6500, 15, 5, 'pcs'],
            ['PRD-158', 'SilverQueen Chunky Bar 30g', 'Snacks', 11000, 16, 6, 'pcs'],
            ['PRD-159', 'SilverQueen Almond 55g', 'Snacks', 17500, 12, 5, 'pcs'],
            ['PRD-160', 'Permen Kopiko Bungkus 150g', 'Snacks', 11000, 18, 6, 'pack'],
            ['PRD-161', 'Permen Relaxa Barley Mint 125g', 'Snacks', 9500, 16, 6, 'pack'],
            ['PRD-162', 'Permen Kiss Mint 125g', 'Snacks', 9500, 16, 6, 'pack'],
            ['PRD-163', 'Yupi Gummy Strawberry Kiss', 'Snacks', 5000, 24, 8, 'pack'],
            ['PRD-164', 'Astor Wafer Stick 40g', 'Snacks', 7500, 16, 6, 'box'],
            ['PRD-165', 'Regal Marie 230g', 'Snacks', 19500, 12, 4, 'pack'],

            // Household Products (35 items: PRD-166 .. PRD-200)
            ['PRD-166', 'Sabun Lifebuoy Total 10 (70g)', 'Household Products', 4500, 35, 10, 'pcs'],
            ['PRD-167', 'Sabun Lifebuoy Cair Refill 400ml', 'Household Products', 24000, 14, 5, 'pack'],
            ['PRD-168', 'Sabun Dettol Original 100g', 'Household Products', 7500, 20, 6, 'pcs'],
            ['PRD-169', 'Sabun Nuvo Family 72g', 'Household Products', 3500, 32, 10, 'pcs'],
            ['PRD-170', 'Sabun Giv White 76g', 'Household Products', 3500, 28, 10, 'pcs'],
            ['PRD-171', 'Shampo Sunsilk Hitam Sachet', 'Household Products', 1000, 90, 25, 'pcs'],
            ['PRD-172', 'Shampo Pantene Anti Ketombe Sachet', 'Household Products', 1000, 85, 25, 'pcs'],
            ['PRD-173', 'Shampo Clear Men Sachet', 'Household Products', 1000, 80, 25, 'pcs'],
            ['PRD-174', 'Shampo Zinc 170ml', 'Household Products', 22000, 12, 5, 'bottle'],
            ['PRD-175', 'Pasta Gigi Pepsodent 190g', 'Household Products', 16500, 22, 8, 'pcs'],
            ['PRD-176', 'Pasta Gigi Pepsodent 75g', 'Household Products', 7500, 26, 8, 'pcs'],
            ['PRD-177', 'Pasta Gigi Ciptadent 190g', 'Household Products', 13500, 18, 6, 'pcs'],
            ['PRD-178', 'Sikat Gigi Formula Soft', 'Household Products', 6500, 24, 8, 'pcs'],
            ['PRD-179', 'Rinso Anti Noda 770g', 'Household Products', 24500, 18, 6, 'pack'],
            ['PRD-180', 'Rinso Cair Molto Sachet', 'Household Products', 1000, 95, 25, 'pcs'],
            ['PRD-181', 'Daia Detergen Bunga 850g', 'Household Products', 19500, 20, 6, 'pack'],
            ['PRD-182', 'So Klin Liquid 720ml', 'Household Products', 18500, 16, 6, 'pack'],
            ['PRD-183', 'So Klin Lantai Lavender 770ml', 'Household Products', 14500, 18, 6, 'pack'],
            ['PRD-184', 'Sunlight Jeruk Nipis 400ml', 'Household Products', 11000, 24, 8, 'pack'],
            ['PRD-185', 'Mama Lemon 680ml', 'Household Products', 13500, 18, 6, 'pack'],
            ['PRD-186', 'Sabun Colek Ekonomi 500g', 'Household Products', 7500, 22, 8, 'pcs'],
            ['PRD-187', 'Molto Pewangi Sachet', 'Household Products', 1000, 100, 30, 'pcs'],
            ['PRD-188', 'Downy Sunrise Fresh 650ml', 'Household Products', 26000, 12, 4, 'pack'],
            ['PRD-189', 'Kispray Pelicin Pakaian 280ml', 'Household Products', 8500, 20, 6, 'pack'],
            ['PRD-190', 'Baygon Aerosol 600ml', 'Household Products', 42000, 10, 4, 'pcs'],
            ['PRD-191', 'Obat Nyamuk Bakar Tiga Roda', 'Household Products', 5500, 28, 10, 'box'],
            ['PRD-192', 'Tissue Paseo Smart 250 Sheets', 'Household Products', 13500, 22, 8, 'pack'],
            ['PRD-193', 'Tissue Nice Facial 180 Sheets', 'Household Products', 10500, 24, 8, 'pack'],
            ['PRD-194', 'Tissue Jolly 250 Sheets', 'Household Products', 11000, 20, 6, 'pack'],
            ['PRD-195', 'Pembalut Charm Body Fit 10s', 'Household Products', 9500, 18, 6, 'pack'],
            ['PRD-196', 'Pembalut Laurier Active Day 10s', 'Household Products', 9500, 16, 6, 'pack'],
            ['PRD-197', 'Popok Sweety Bronze Pants M20', 'Household Products', 36000, 10, 4, 'pack'],
            ['PRD-198', 'Popok MamyPoko Pants L20', 'Household Products', 42000, 10, 4, 'pack'],
            ['PRD-199', 'Baterai ABC Biru AA (2 pcs)', 'Household Products', 7500, 25, 8, 'pack'],
            ['PRD-200', 'Korek Api Gas Tokai', 'Household Products', 3500, 40, 12, 'pcs'],
        ];

        foreach ($items as [$code, $name, $categoryName, $price, $stock, $minStock, $unit]) {
            $product = Product::firstOrCreate(
                ['product_code' => $code],
                [
                    'category_id' => $categories[$categoryName]->id,
                    'product_name' => $name,
                    'description' => 'Produk sembako UMKM: ' . $name,
                    'price' => $price,
                    'stock' => $stock,
                    'minimum_stock' => $minStock,
                    'unit' => $unit,
                    'is_active' => true,
                ]
            );

            StockMovement::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'movement_type' => StockMovementType::StockIn,
                    'note' => 'Initial sembako catalog stock',
                ],
                [
                    'user_id' => $admin?->id,
                    'transaction_id' => null,
                    'quantity' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                ]
            );
        }
    }
}
