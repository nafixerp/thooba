<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\AppVersion;
use App\Models\Bank;
use App\Models\Banner;
use App\Models\BannerContent;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Country;
use App\Models\CustomMessage;
use App\Models\District;
use App\Models\Metal;
use App\Models\MetalRate;
use App\Models\Offer;
use App\Models\Product;
use App\Models\PrivilegeTier;
use App\Models\Role;
use App\Models\Setting;
use App\Models\State;
use App\Models\Team;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generic CRUD for all the simple master screens.
 * Route: /admin/master/{entity}
 */
class MasterController extends Controller
{
    /** entity => [Model class, validation rules, upload fields, eager loads, title] */
    protected array $map = [
        'countries' => [Country::class, ['code' => 'required|max:5', 'name' => 'required|max:100', 'phone_code' => 'nullable|max:8'], [], [], 'Countries'],
        'branches' => [Branch::class, ['code' => 'required|max:30', 'name' => 'required|max:150', 'manager_name' => 'nullable|max:150', 'phone' => 'nullable|max:20', 'email' => 'nullable|email|max:120', 'address' => 'nullable', 'city' => 'nullable|max:100', 'state' => 'nullable|max:100', 'country' => 'nullable|max:100', 'pincode' => 'nullable|max:10'], [], [], 'Branches'],
        'roles' => [Role::class, ['name' => 'required|max:80', 'slug' => 'required|max:80', 'description' => 'nullable'], [], [], 'Roles & Permissions'],
        'states' => [State::class, ['country_id' => 'required|exists:countries,id', 'name' => 'required|max:100', 'code' => 'nullable|max:10'], [], ['country'], 'States'],
        'districts' => [District::class, ['state_id' => 'required|exists:states,id', 'name' => 'required|max:100'], [], ['state.country'], 'Districts'],
        'metals' => [Metal::class, ['name' => 'required|max:60', 'code' => 'required|max:20', 'purity' => 'nullable|max:20'], [], [], 'Metals'],
        'metal-rates' => [MetalRate::class, ['metal_id' => 'required|exists:metals,id', 'rate' => 'required|numeric|min:0', 'rate_date' => 'required|date'], [], ['metal'], 'Metal Rates'],
        'categories' => [Category::class, ['name' => 'required|max:100'], ['image' => 'categories'], [], 'Categories'],
        'products' => [Product::class, ['name' => 'required|max:150', 'category_id' => 'nullable|exists:categories,id', 'weight' => 'nullable|numeric', 'making_charge' => 'nullable|numeric', 'price' => 'nullable|numeric', 'description' => 'nullable'], ['image' => 'products'], ['category'], 'Products'],
        'offers' => [Offer::class, ['title' => 'required|max:150', 'product_id' => 'nullable|exists:products,id', 'offer_code' => 'nullable|max:40', 'discount' => 'nullable|numeric', 'description' => 'nullable', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date'], ['image' => 'offers'], ['product'], 'Offers'],
        'banners' => [Banner::class, ['title' => 'nullable|max:150', 'link' => 'nullable|max:255'], ['image' => 'banners'], [], 'Banner Images'],
        'banner-contents' => [BannerContent::class, ['title' => 'required|max:150', 'description' => 'nullable'], ['image' => 'banners'], [], 'Banner Content'],
        'terms' => [Term::class, ['title' => 'required|max:200', 'description' => 'nullable', 'type' => 'nullable'], [], [], 'Terms & Conditions'],
        'teams' => [Team::class, ['name' => 'required|max:120', 'designation' => 'nullable|max:120', 'phone' => 'nullable|max:20', 'email' => 'nullable|email'], ['photo' => 'teams'], [], 'Our Team'],
        'banks' => [Bank::class, ['bank_name' => 'required|max:120', 'beneficiary_name' => 'required|max:120', 'ifsc' => 'required|max:20', 'account_no' => 'required|max:40', 'branch' => 'nullable|max:120', 'upi_id' => 'nullable|max:80', 'accode' => 'nullable|exists:accountm,accode'], ['qr_image' => 'banks'], [], 'Bank Details'],
        'commission-rules' => [CommissionRule::class, ['name' => 'required|max:120', 'branch_id' => 'nullable|exists:branches,id', 'scheme_id' => 'nullable|exists:schemes,id', 'basis' => 'required|in:PERCENT,FLAT', 'rate' => 'required|numeric|min:0', 'effective_from' => 'nullable|date', 'effective_to' => 'nullable|date'], [], ['branch', 'scheme'], 'Commission Rules'],
        'privilege-tiers' => [PrivilegeTier::class, ['name' => 'required|max:80', 'basis' => 'required|in:AMOUNT,GRAM', 'min_sales' => 'nullable|required_if:basis,AMOUNT|numeric|min:0', 'min_gram' => 'nullable|required_if:basis,GRAM|numeric|min:0', 'points_per_100' => 'nullable|required_if:basis,AMOUNT|numeric|min:0', 'points_per_gram' => 'nullable|required_if:basis,GRAM|numeric|min:0', 'card_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'benefits' => 'nullable', 'sortorder' => 'nullable|integer|min:0'], [], [], 'Privilege Card Tiers'],
        'app-versions' => [AppVersion::class, ['platform' => 'required|in:ANDROID,IOS,WEB', 'version' => 'required|max:30', 'build_number' => 'required|integer|min:1', 'min_supported_version' => 'nullable|max:30', 'min_supported_build' => 'nullable|integer|min:1', 'store_url' => 'nullable|max:255', 'release_notes' => 'nullable'], [], [], 'App Versions'],
        'messages' => [CustomMessage::class, ['title' => 'required|max:150', 'message' => 'required', 'channel' => 'nullable', 'target' => 'nullable'], [], [], 'Custom Messages'],
        'users' => [User::class, ['name' => 'required|max:150', 'username' => 'required|max:60', 'email' => 'required|email', 'role' => 'required', 'phone' => 'nullable|max:20', 'password' => 'nullable|min:6'], ['photo' => 'users'], [], 'Users'],
    ];

    protected function resolve(string $entity): array
    {
        abort_unless(isset($this->map[$entity]), 404);

        return $this->map[$entity];
    }

    public function index(Request $request, string $entity)
    {
        if ($entity === 'users') {
            $this->ensureSuperAdmin($request);
        }

        [$model, , , $with, $title] = $this->resolve($entity);

        $rows = $model::with($with)
            ->when($request->q, function ($q, $v) use ($model) {
                $col = in_array('name', (new $model)->getFillable()) ? 'name'
                    : ((new $model)->getFillable()[0] ?? 'id');

                return $q->where($col, 'like', "%$v%");
            })
            ->latest('id')
            ->paginate(25)->withQueryString();

        return view('admin.masters.index', [
            'rows' => $rows,
            'entity' => $entity,
            'title' => $title,
            'columns' => $this->columns($entity),
            'options' => $this->options($entity),
            'fields' => $this->fields($entity),
        ]);
    }

    public function store(Request $request, string $entity, $id = null)
    {
        if ($entity === 'users') {
            $this->ensureSuperAdmin($request);
        }

        [$model, $rules, $uploads] = $this->resolve($entity);

        // Validate the file inputs as well. An upload PHP itself refused - over
        // upload_max_filesize, or an unwritable upload_tmp_dir - arrives as an
        // invalid UploadedFile, hasFile() answers false, and without a rule here
        // the row saved with no image under a green "saved" toast.
        foreach (array_keys($uploads) as $field) {
            $rules[$field] = 'nullable|file|max:8192';
        }

        $data = $request->validate($rules);

        foreach ($uploads as $field => $folder) {
            if (! $request->hasFile($field)) {
                // No new file on an edit: leave whatever is already stored alone
                // instead of writing the UploadedFile placeholder over it.
                unset($data[$field]);

                continue;
            }

            $path = $request->file($field)->store($folder, 'public');

            // The public disk is configured with 'throw' => false, so a failed
            // write returns false rather than raising.
            if ($path === false) {
                return back()
                    ->withInput()
                    ->withErrors([$field => 'Could not save the file. Check that storage/app/public/'.$folder.' exists and is writable.']);
            }

            $data[$field] = $path;
        }

        if (in_array('status', (new $model)->getFillable())) {
            $data['status'] = $request->boolean('status', true);
        }

        if ($entity === 'users' && empty($data['password'])) {
            unset($data['password']);
        }

        if ($entity === 'app-versions') {
            $data['force_update'] = $request->boolean('force_update');
            $data['maintenance_mode'] = $request->boolean('maintenance_mode');
        }

        $id ? $model::findOrFail($id)->update($data) : $model::create($data);

        return back()->with('success', Str::headline(Str::singular($entity)).' saved.');
    }

    public function destroy(string $entity, $id)
    {
        [$model] = $this->resolve($entity);

        if ($entity === 'users') {
            $this->ensureSuperAdmin(request());

            if ((int) $id === (int) request()->user()->id) {
                return back()->withErrors(['user' => 'Current signed-in user cannot be deleted.']);
            }
        }

        $model::findOrFail($id)->delete();

        return back()->with('success', 'Record deleted.');
    }

    public function toggle(string $entity, $id)
    {
        [$model] = $this->resolve($entity);

        if ($entity === 'users') {
            $this->ensureSuperAdmin(request());
        }

        $row = $model::findOrFail($id);
        $row->update(['status' => ! $row->status]);

        return back()->with('success', 'Status updated.');
    }

    /* ---------------- Table columns per entity ---------------- */
    protected function columns(string $entity): array
    {
        return match ($entity) {
            'countries' => ['code' => 'Code', 'name' => 'Country', 'phone_code' => 'Phone Code'],
            'branches' => ['code' => 'Code', 'name' => 'Branch', 'manager_name' => 'Manager', 'phone' => 'Phone', 'city' => 'City'],
            'roles' => ['name' => 'Role', 'slug' => 'Slug', 'description' => 'Description'],
            'states' => ['name' => 'State', 'country.name' => 'Country', 'code' => 'Code'],
            'districts' => ['name' => 'District', 'state.name' => 'State', 'state.country.name' => 'Country'],
            'metals' => ['name' => 'Metal', 'code' => 'Code', 'purity' => 'Purity'],
            'metal-rates' => ['metal.name' => 'Metal', 'rate' => 'Rate / gram', 'rate_date' => 'Date'],
            'categories' => ['image' => 'Image', 'name' => 'Category'],
            'products' => ['image' => 'Image', 'name' => 'Product', 'category.name' => 'Category', 'price' => 'Price'],
            'offers' => ['title' => 'Title', 'product.name' => 'Product', 'offer_code' => 'Code', 'discount' => 'Discount'],
            'banners' => ['image' => 'Image', 'title' => 'Title'],
            'banner-contents' => ['image' => 'Image', 'title' => 'Title', 'description' => 'Description'],
            'terms' => ['title' => 'Title', 'type' => 'Type'],
            'teams' => ['photo' => 'Photo', 'name' => 'Name', 'designation' => 'Designation', 'phone' => 'Phone'],
            'banks' => ['bank_name' => 'Bank', 'beneficiary_name' => 'Beneficiary', 'ifsc' => 'IFSC', 'account_no' => 'Account No'],
            'commission-rules' => ['name' => 'Rule', 'branch.name' => 'Branch', 'scheme.name' => 'Scheme', 'basis' => 'Basis', 'rate' => 'Rate'],
            'privilege-tiers' => ['name' => 'Card', 'basis_label' => 'Basis', 'minimum_label' => 'Starts From', 'reward_rate_label' => 'Point Rate', 'card_color' => 'Colour', 'benefits' => 'Benefits'],
            'app-versions' => ['platform' => 'Platform', 'version' => 'Version', 'build_number' => 'Build', 'force_update' => 'Force Update'],
            'messages' => ['title' => 'Title', 'channel' => 'Channel', 'target' => 'Target'],
            'users' => ['photo' => 'Photo', 'name' => 'Name', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role'],
            default => ['name' => 'Name'],
        };
    }

    /* ---------------- Form fields per entity ---------------- */
    protected function fields(string $entity): array
    {
        return match ($entity) {
            'countries' => [
                ['name' => 'code', 'label' => 'Country Code', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => 'Country Name', 'type' => 'text', 'required' => true],
                ['name' => 'phone_code', 'label' => 'Phone Code', 'type' => 'text'],
            ],
            'branches' => [
                ['name' => 'code', 'label' => 'Branch Code', 'type' => 'text', 'required' => true],
                ['name' => 'name', 'label' => 'Branch Name', 'type' => 'text', 'required' => true],
                ['name' => 'manager_name', 'label' => 'Manager', 'type' => 'text'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'city', 'label' => 'City', 'type' => 'text'],
                ['name' => 'state', 'label' => 'State', 'type' => 'text'],
                ['name' => 'country', 'label' => 'Country', 'type' => 'text'],
                ['name' => 'pincode', 'label' => 'Pincode', 'type' => 'text'],
                ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
            ],
            'roles' => [
                ['name' => 'name', 'label' => 'Role Name', 'type' => 'text', 'required' => true],
                ['name' => 'slug', 'label' => 'Slug', 'type' => 'text', 'required' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ],
            'states' => [
                ['name' => 'country_id', 'label' => 'Country', 'type' => 'select', 'options' => 'countries', 'required' => true],
                ['name' => 'name', 'label' => 'State Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
            ],
            'districts' => [
                ['name' => 'state_id', 'label' => 'State', 'type' => 'select', 'options' => 'states', 'required' => true],
                ['name' => 'name', 'label' => 'District Name', 'type' => 'text', 'required' => true],
            ],
            'metals' => [
                ['name' => 'name', 'label' => 'Metal Name', 'type' => 'text', 'required' => true],
                ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true],
                ['name' => 'purity', 'label' => 'Purity', 'type' => 'text'],
            ],
            'metal-rates' => [
                ['name' => 'metal_id', 'label' => 'Metal', 'type' => 'select', 'options' => 'metals', 'required' => true],
                ['name' => 'rate', 'label' => 'Rate per gram', 'type' => 'number', 'step' => '0.01', 'required' => true],
                ['name' => 'rate_date', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ],
            'categories' => [
                ['name' => 'name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
                ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
            ],
            'products' => [
                ['name' => 'name', 'label' => 'Product Name', 'type' => 'text', 'required' => true],
                ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => 'categories'],
                ['name' => 'weight', 'label' => 'Weight (g)', 'type' => 'number', 'step' => '0.001'],
                ['name' => 'making_charge', 'label' => 'Making Charge', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'price', 'label' => 'Price', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
            ],
            'offers' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['name' => 'product_id', 'label' => 'Product', 'type' => 'select', 'options' => 'products'],
                ['name' => 'offer_code', 'label' => 'Offer Code', 'type' => 'text'],
                ['name' => 'discount', 'label' => 'Discount', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
                ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
            ],
            'banners' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['name' => 'link', 'label' => 'Link', 'type' => 'text'],
                ['name' => 'image', 'label' => 'Banner Image', 'type' => 'file'],
            ],
            'banner-contents' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'image', 'label' => 'Image', 'type' => 'file'],
            ],
            'terms' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => 'term_types'],
                ['name' => 'description', 'label' => 'Content', 'type' => 'textarea', 'rows' => 8],
            ],
            'teams' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['name' => 'designation', 'label' => 'Designation', 'type' => 'text'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                ['name' => 'photo', 'label' => 'Photo', 'type' => 'file'],
            ],
            'banks' => [
                ['name' => 'bank_name', 'label' => 'Bank Name', 'type' => 'text', 'required' => true],
                ['name' => 'beneficiary_name', 'label' => 'Beneficiary Name', 'type' => 'text', 'required' => true],
                ['name' => 'ifsc', 'label' => 'IFSC Code', 'type' => 'text', 'required' => true],
                ['name' => 'account_no', 'label' => 'Account Number', 'type' => 'text', 'required' => true],
                ['name' => 'branch', 'label' => 'Branch', 'type' => 'text'],
                ['name' => 'upi_id', 'label' => 'UPI ID', 'type' => 'text'],
                ['name' => 'accode', 'label' => 'Linked Ledger', 'type' => 'select', 'options' => 'bank_ledgers'],
                ['name' => 'qr_image', 'label' => 'QR Image', 'type' => 'file'],
            ],
            'commission-rules' => [
                ['name' => 'name', 'label' => 'Rule Name', 'type' => 'text', 'required' => true],
                ['name' => 'branch_id', 'label' => 'Branch', 'type' => 'select', 'options' => 'branches'],
                ['name' => 'scheme_id', 'label' => 'Scheme', 'type' => 'select', 'options' => 'schemes'],
                ['name' => 'basis', 'label' => 'Basis', 'type' => 'select', 'options' => 'commission_basis', 'required' => true],
                ['name' => 'rate', 'label' => 'Rate', 'type' => 'number', 'step' => '0.01', 'required' => true],
                ['name' => 'effective_from', 'label' => 'Effective From', 'type' => 'date'],
                ['name' => 'effective_to', 'label' => 'Effective To', 'type' => 'date'],
            ],
            'privilege-tiers' => [
                ['name' => 'name', 'label' => 'Card Name', 'type' => 'text', 'required' => true],
                ['name' => 'basis', 'label' => 'Calculation Basis', 'type' => 'select', 'options' => 'privilege_basis', 'required' => true],
                ['name' => 'min_sales', 'label' => 'Minimum Sales Amount (amount basis)', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'points_per_100', 'label' => 'Points per Rs 100 (amount basis)', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'min_gram', 'label' => 'Minimum Gold Gram (gram basis)', 'type' => 'number', 'step' => '0.001'],
                ['name' => 'points_per_gram', 'label' => 'Points per Gram (gram basis)', 'type' => 'number', 'step' => '0.01'],
                ['name' => 'card_color', 'label' => 'Card Colour', 'type' => 'color', 'required' => true],
                ['name' => 'sortorder', 'label' => 'Display Order', 'type' => 'number'],
                ['name' => 'benefits', 'label' => 'Benefits', 'type' => 'textarea'],
            ],
            'app-versions' => [
                ['name' => 'platform', 'label' => 'Platform', 'type' => 'select', 'options' => 'platforms', 'required' => true],
                ['name' => 'version', 'label' => 'Version', 'type' => 'text', 'required' => true],
                ['name' => 'build_number', 'label' => 'Build Number', 'type' => 'number', 'required' => true],
                ['name' => 'min_supported_version', 'label' => 'Min Supported Version', 'type' => 'text'],
                ['name' => 'min_supported_build', 'label' => 'Min Supported Build', 'type' => 'number'],
                ['name' => 'store_url', 'label' => 'Store URL', 'type' => 'text'],
                ['name' => 'release_notes', 'label' => 'Release Notes', 'type' => 'textarea'],
                ['name' => 'force_update', 'label' => 'Force Update', 'type' => 'checkbox'],
                ['name' => 'maintenance_mode', 'label' => 'Maintenance Mode', 'type' => 'checkbox'],
            ],
            'messages' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                ['name' => 'channel', 'label' => 'Channel', 'type' => 'select', 'options' => 'channels'],
                ['name' => 'target', 'label' => 'Target', 'type' => 'select', 'options' => 'targets'],
                ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'rows' => 4, 'required' => true],
            ],
            'users' => [
                ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                ['name' => 'username', 'label' => 'Username', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => 'roles'],
                ['name' => 'password', 'label' => 'Password', 'type' => 'password'],
                ['name' => 'photo', 'label' => 'Photo', 'type' => 'file'],
            ],
            default => [['name' => 'name', 'label' => 'Name', 'type' => 'text']],
        };
    }

    /* ---------------- Select options ---------------- */
    protected function options(string $entity): array
    {
        return [
            'countries' => Country::orderBy('name')->pluck('name', 'id')->all(),
            'branches' => Branch::orderBy('name')->pluck('name', 'id')->all(),
            'states' => State::orderBy('name')->pluck('name', 'id')->all(),
            'metals' => Metal::orderBy('name')->pluck('name', 'id')->all(),
            'schemes' => \App\Models\Scheme::orderBy('name')->pluck('name', 'id')->all(),
            'categories' => Category::orderBy('name')->pluck('name', 'id')->all(),
            'products' => Product::orderBy('name')->pluck('name', 'id')->all(),
            'bank_ledgers' => \App\Models\Accountm::whereIn('actype', ['BANK', 'CASH'])->pluck('name', 'accode')->all(),
            'roles' => ['SUPERADMIN' => 'Super Admin', 'ADMIN' => 'Admin', 'ACCOUNTANT' => 'Accountant', 'STAFF' => 'Staff'],
            'channels' => ['PUSH' => 'Push', 'SMS' => 'SMS', 'EMAIL' => 'Email', 'WHATSAPP' => 'WhatsApp'],
            'targets' => ['ALL' => 'All', 'CUSTOMER' => 'Customers', 'AGENT' => 'Agents'],
            'term_types' => ['TERMS' => 'Terms', 'PRIVACY' => 'Privacy', 'REFUND' => 'Refund', 'FAQ' => 'FAQ'],
            'commission_basis' => ['PERCENT' => 'Percent', 'FLAT' => 'Flat Amount'],
            'privilege_basis' => ['AMOUNT' => 'Sales Amount', 'GRAM' => 'Gold Gram'],
            'platforms' => ['ANDROID' => 'Android', 'IOS' => 'iOS', 'WEB' => 'Web'],
        ];
    }

    /* ---------------- Settings / About ---------------- */

    public function settings()
    {
        $settings = Setting::pluck('value', 'key');
        $about = About::first() ?? new About;
        $sqlResult = session('sql_result');

        return view('admin.masters.settings', compact('settings', 'about', 'sqlResult'));
    }

    public function saveSettings(Request $request)
    {
        foreach ($request->except(['_token', 'about_title', 'about_description', 'about_image']) as $k => $v) {
            if (Str::contains($k, 'prefix')) {
                $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));
            }

            Setting::put($k, $v, 'company');
        }

        if ($request->filled('about_title')) {
            $about = About::first() ?? new About;
            $about->fill([
                'title' => $request->about_title,
                'description' => $request->about_description,
            ]);
            if ($request->hasFile('about_image')) {
                $about->image = $request->file('about_image')->store('about', 'public');
            }
            $about->save();
        }

        return back()->with('success', 'Settings saved.');
    }

    /** Upload the Android installer served by the permanent public download URL. */
    public function saveAndroidApp(Request $request)
    {
        $data = $request->validate([
            'apk' => ['required', 'file', 'max:204800'],
            'apk_version' => ['nullable', 'string', 'max:30'],
        ]);

        $file = $request->file('apk');
        if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
            return back()->withErrors(['apk' => 'Please upload an Android .apk file.']);
        }

        $path = $file->storeAs('releases', 'scheme-erp.apk', 'public');
        $version = trim((string) ($data['apk_version'] ?? '')) ?: now()->format('Y.m.d');

        Setting::put('android_apk_path', $path, 'mobile');
        Setting::put('android_apk_version', $version, 'mobile');
        Setting::put('android_apk_size', (string) Storage::disk('public')->size($path), 'mobile');
        Setting::put('android_apk_updated_at', now()->toIso8601String(), 'mobile');

        return back()->with('success', 'Android app uploaded. The public download link is ready.');
    }

    public function runSql(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'sql' => ['required', 'string', 'max:10000'],
            'password' => ['required', 'string'],
        ]);

        $this->confirmPassword($request, $data['password']);

        $sql = trim($data['sql']);
        if ($this->hasMultipleStatements($sql)) {
            return back()->withErrors(['sql' => 'Only one SQL statement can be run at a time.']);
        }

        $verb = strtoupper(strtok(ltrim($sql), " \t\r\n"));
        $dangerous = in_array($verb, ['DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'RENAME'], true);
        if ($dangerous && $request->input('confirm_phrase') !== 'RUN SQL') {
            return back()->withErrors(['sql' => 'Type RUN SQL to run schema-level SQL.']);
        }

        try {
            if (in_array($verb, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
                $rows = collect(DB::select($sql))->map(fn ($row) => (array) $row)->all();
                $columns = $rows ? array_keys($rows[0]) : [];
                $result = ['type' => 'select', 'columns' => $columns, 'rows' => array_slice($rows, 0, 100), 'count' => count($rows)];
            } else {
                $affected = DB::affectingStatement($sql);
                $result = ['type' => 'statement', 'affected' => $affected];
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['sql' => $e->getMessage()])->withInput();
        }

        return back()->with('success', 'SQL executed.')->with('sql_result', $result);
    }

    /**
     * Everything "Clear all data" wipes: the shop's own entries and the people
     * they belong to. Children first, so the deletes stay sane even where
     * foreign keys are still on.
     *
     * A table left out of this AND out of KEPT_TABLES is caught by
     * tests/Feature/ResetDataTest.php, which is what stops a new table
     * quietly surviving a reset and leaving rows pointing at customers who no
     * longer exist.
     */
    public const CLEARED_TABLES = [
        // scheme + customer activity
        'referrals', 'point_transactions', 'points', 'agent_payouts',
        'notifications', 'audit_logs', 'terms_acceptances', 'document_verifications',
        'kyc_documents', 'customer_ledgers',
        'support_ticket_replies', 'support_tickets', 'agent_commissions',
        'refunds', 'scheme_closures',

        // money in: the statement lines and gateway orders point at payments,
        // so they go before payments does.
        'bank_statement_lines', 'payment_webhook_logs', 'payment_orders', 'payments',

        'installments', 'customer_schemes', 'customers', 'agents',

        // accounts
        'daybook', 'daybookpart', 'accountm', 'accountg', 'accountgbs',
        'stock_values',

        // credentials
        'otp_codes', 'personal_access_tokens',
    ];

    /**
     * Deliberately kept: the shop's own setup, which a data reset is not meant
     * to undo. Re-seeded where a seeder owns it.
     */
    public const KEPT_TABLES = [
        'schemes', 'banks', 'metals', 'metal_rates', 'privilege_tiers',
        'products', 'categories', 'offers', 'banners', 'banner_contents',
        'terms', 'teams', 'abouts', 'custom_messages', 'settings',
        'countries', 'states', 'districts',
        'roles', 'permissions', 'permission_role',
        'branches', 'commission_rules', 'app_versions',
        // handled separately inside resetData
        'users', 'sessions',
    ];

    public function resetData(Request $request)
    {
        $this->ensureSuperAdmin($request);

        $data = $request->validate([
            'password' => ['required', 'string'],
            'confirm_phrase' => ['required', 'string'],
        ]);

        $this->confirmPassword($request, $data['password']);

        if ($data['confirm_phrase'] !== 'RESET DATA') {
            return back()->withErrors(['reset' => 'Type RESET DATA to clear all business entries.']);
        }

        $currentUser = $request->user();
        $currentSessionId = $request->session()->getId();

        // NOT wrapped in DB::transaction(). In MySQL every TRUNCATE performs an
        // implicit COMMIT, so the framework's own commit at the end of the
        // closure blew up with "There is no active transaction" and the reset
        // died half way through.
        //
        // Schema::disableForeignKeyConstraints() rather than a raw
        // SET FOREIGN_KEY_CHECKS: that statement is MySQL only and made the
        // whole reset throw a 500 on SQLite, which this project supports.
        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::CLEARED_TABLES as $table) {
                // Older deployments do not have every table yet; skip instead of aborting.
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }

            // Sign every other device out, but keep the row backing THIS request:
            // truncating the whole table logs the super admin out mid-reset and
            // the success page just bounces back to /login.
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('id', '!=', $currentSessionId)->delete();
            }

            User::whereKeyNot($currentUser->id)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // The chart of accounts only, because accountm/accountg/accountgbs were
        // just truncated and auto posting needs its control ledgers back.
        //
        // NOT MasterDataSeeder. It carries demo data - "Ishq Jewellery", three
        // sample schemes, an SBI account, admin@scheme.test - and its
        // Setting::put calls overwrite, so running it here renamed the shop
        // back to the demo name and reset the admin password on every clear.
        // None of the master tables are truncated, so there is nothing for it
        // to restore.
        $this->callSeeder(ChartOfAccountsSeeder::class);

        return redirect()->route('admin.settings')->with('success', 'Business data cleared. Only the current super admin user is kept.');
    }

    protected function ensureSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'SUPERADMIN', 403, 'Super admin access required.');
    }

    protected function confirmPassword(Request $request, string $password): void
    {
        abort_unless(Hash::check($password, $request->user()->password), 403, 'Password confirmation failed.');
    }

    protected function hasMultipleStatements(string $sql): bool
    {
        return (bool) preg_match('/;\\s*\\S/', rtrim($sql));
    }

    protected function callSeeder(string $seeder): void
    {
        app($seeder)->run();
    }
}
