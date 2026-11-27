<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\View\View;

class PlaceholderController extends Controller
{
    public function pos(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'POS / Transactions',
            'breadcrumb' => 'POS / Transactions',
            'moduleName' => 'Point of Sale Cashier Terminal',
            'description' => 'This module will provide fast checkout, barcode/AI-assisted item lookup, and receipt generation in the next development phase.',
            'plannedFeatures' => [
                'Fast cart management and quantity controls',
                'AI-assisted product matching and order confirmation',
                'Cash, QRIS, Debit, and Transfer payment recording',
            ],
        ]);
    }

    public function products(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Products',
            'breadcrumb' => 'Products',
            'moduleName' => 'Product & Inventory Management',
            'description' => 'This administrator-only module will manage store SKUs, selling prices, stock levels, and minimum stock alerts.',
            'plannedFeatures' => [
                'Create, update, and archive store products',
                'Configure minimum stock thresholds per item',
                'Unit and category assignment for toko kelontong inventory',
            ],
        ]);
    }

    public function categories(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Categories',
            'breadcrumb' => 'Categories',
            'moduleName' => 'Product Category Management',
            'description' => 'This administrator-only module will organize grocery items into structured retail categories.',
            'plannedFeatures' => [
                'Create and edit retail product categories',
                'Track item counts and inventory grouping',
            ],
        ]);
    }

    public function transactions(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Transaction History',
            'breadcrumb' => 'Transaction History',
            'moduleName' => 'Sales & Receipt History',
            'description' => 'This module will provide full transaction filtering, receipt inspection, and daily sales reconciliation.',
            'plannedFeatures' => [
                'Search by transaction code, date range, and cashier',
                'Filter by payment method and transaction status',
                'Detailed receipt preview and print layout',
            ],
        ]);
    }

    public function transactionShow(Transaction $transaction): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Transaction Details',
            'breadcrumb' => 'Transaction History / ' . $transaction->transaction_code,
            'moduleName' => 'Transaction Detail Preview (' . $transaction->transaction_code . ')',
            'description' => 'Full receipt breakdown and payment reconciliation for this transaction will be implemented in the Transaction History phase.',
            'plannedFeatures' => [
                'Itemized product quantities and subtotals',
                'Cashier attribution and payment method verification',
                'Printable thermal receipt export',
            ],
        ]);
    }

    public function users(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Users',
            'breadcrumb' => 'Users',
            'moduleName' => 'User & Role Management',
            'description' => 'This administrator-only module will manage store owner and cashier accounts.',
            'plannedFeatures' => [
                'Create and deactivate cashier accounts',
                'Assign administrator or cashier roles',
                'Reset staff credentials securely',
            ],
        ]);
    }

    public function settings(): View
    {
        return view('placeholders.module', [
            'pageTitle' => 'Settings',
            'breadcrumb' => 'Settings',
            'moduleName' => 'Store & Application Settings',
            'description' => 'Store profile, receipt header details, and POS preferences will be configured here in a future phase.',
            'plannedFeatures' => [
                'UMKM store name, address, and receipt footer',
                'Tax/rounding and default payment preferences',
                'AI assistant configuration in later phases',
            ],
        ]);
    }
}
