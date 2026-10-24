<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\Printer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('printer:create
    {name : Printer name, e.g. "Brother casa"}
    {--location=Matriz : Location name; created if it does not exist}
    {--color : The printer can print in color}')]
#[Description('Register a printer and print its agent token (shown only once)')]
class CreatePrinter extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $location = Location::firstOrCreate(['name' => $this->option('location')]);

        $printer = new Printer([
            'location_id' => $location->id,
            'name' => $this->argument('name'),
            'supports_color' => (bool) $this->option('color'),
        ]);
        $token = $printer->issueToken();

        $this->info("Printer #{$printer->id} \"{$printer->name}\" created at location \"{$location->name}\".");
        $this->newLine();
        $this->line('Agent token (copy it now, it will not be shown again):');
        $this->line($token);

        return self::SUCCESS;
    }
}
