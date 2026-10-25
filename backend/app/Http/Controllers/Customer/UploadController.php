<?php

namespace App\Http\Controllers\Customer;

use App\Enums\Channel;
use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreUploadRequest;
use App\Models\Customer;
use App\Models\Location;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public page where customers send a PDF from their phone (link or QR at the counter).
 */
class UploadController extends Controller
{
    public function create(): View
    {
        $location = Location::forCustomerUploads();

        return view('customer.uploads.create', [
            'location' => $location,
            'supportsColor' => $location?->supportsColor() ?? false,
        ]);
    }

    public function store(StoreUploadRequest $request, PrintJobService $service): RedirectResponse
    {
        $location = Location::forCustomerUploads();
        abort_if($location === null, 503);

        $customer = Customer::firstOrCreate(['phone' => $request->phone()]);
        if ($request->filled('name')) {
            $customer->update(['profile_name' => $request->string('name')->trim()->toString()]);
        }

        $file = $request->file('file');
        $job = $service->submitForCustomer(
            $location,
            $customer,
            $file->getContent(),
            $file->getClientOriginalName(),
            Channel::Web,
            $request->printOptions(),
        );

        return redirect()->route('customer.uploads.create')->with('submitted', [
            'folio' => $job->folio,
            'queued' => $job->status === PrintJobStatus::Queued,
        ]);
    }
}
