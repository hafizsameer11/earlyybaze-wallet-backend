<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeServiceAndRepository extends Command
{
    protected $signature = 'make:service-repository {name}';
    protected $description = 'Create a Service and Repository class for a model';

    protected $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $name = $this->argument('name');
        $this->createRepository($name);
        $this->createService($name);

        $this->info('Service and Repository created successfully.');
    }

    protected function createRepository($name)
    {
        $repositoryPath = app_path("Repositories/{$name}Repository.php");
        $this->makeDirectory($repositoryPath);

        $stub = $this->getRepositoryStub();
        $content = str_replace('{{name}}', $name, $stub);

        $this->files->put($repositoryPath, $content);

        $this->info("Created Repository: {$name}Repository");
    }

    protected function createService($name)
    {
        $servicePath = app_path("Services/{$name}Service.php");
        $this->makeDirectory($servicePath);

        $stub = $this->getServiceStub();
        $content = str_replace('{{name}}', $name, $stub);

        $this->files->put($servicePath, $content);

        $this->info("Created Service: {$name}Service");
    }

    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true);
        }
    }

    protected function getRepositoryStub()
    {
        return <<<EOT
<?php

namespace App\Repositories;

class {{name}}Repository
{
    public function all()
    {
        // Add logic to fetch all data
    }

    public function find(\$id)
    {
        // Add logic to find data by ID
    }

    public function create(array \$data)
    {
        // Add logic to create data
    }

    public function update(\$id, array \$data)
    {
        // Add logic to update data
    }

    public function delete(\$id)
    {
        // Add logic to delete data
    }
}
EOT;
    }

    protected function getServiceStub()
    {
        return <<<EOT
<?php

namespace App\Services;

use App\Repositories\{{name}}Repository;

class {{name}}Service
{
    protected \${{name}}Repository;

    public function __construct({{name}}Repository \${{name}}Repository)
    {
        \$this->{{name}}Repository = \${{name}}Repository;
    }

    public function all()
    {
        return \$this->{{name}}Repository->all();
    }

    public function find(\$id)
    {
        return \$this->{{name}}Repository->find(\$id);
    }

    public function create(array \$data)
    {
        return \$this->{{name}}Repository->create(\$data);
    }

    public function update(\$id, array \$data)
    {
        return \$this->{{name}}Repository->update(\$id, \$data);
    }

    public function delete(\$id)
    {
        return \$this->{{name}}Repository->delete(\$id);
    }
}
EOT;
    }
}
