# Campaigns optimiser test task
This is one of the solutions for the solving test task for Ad Campaigns Optimization Job.

## Features
* Added `Docker Compose` for setup testing environment
    * `redis` container contains Redis DB used for runtime processing of OptimizationJob script
    * `app` container contains PHP CLI with scripts and configurations. It should be used for running given scripts
* Added `composer` for setup PHPUnit and `autoloading`
* Added Xdebug extension for tests, tests coverage, profiling
* Added BCMath extension for more reliant and stable handling math operations. It helps avoid floating point, 
  comparison errors and other inconveniences with calculations on PHP side.
* Useful utilities added
  * `bin/combine` - script for combining all files under `src/*` folder into one output file `output/optimized_job.php`
  * `bin/gen_data` - script for generated randomized data sets for testing performance 
     and durability of `optimized_job.php`. One can pass with arguments number of events and campaigns to generate
  * `bin/run_optimization_job` - script to run OptimizationJob with inputs taken from `./input/*.csv`,
    `output/optimized_job.php` or using `vendor/autoload.php` (can be uncommented in script)
* Added scripts to `composer.json`
  * `app:run` - run script `bin/run_optimization_job` with prepared data sets from `input` folder. Can use `autoloader` 
    or `output/optimized_job.php` script for data processing.
  * `app:run:profile` - run script `bin/run_optimization_job` with Xdebug profiling. Results stored in folder `tests/output`.
  * `app:test` - run unit tests
  * `app:test:cover-txt` - run unit tests with code coverage in TXT format. Results stored in folder `tests/output`.
  * `app:test:cover-html` - run unit tests with code coverage in HTML format
* PHPUnit test coverage - 100%
  ```shell
  $ composer run app:test:cover-txt
    > @php -d 'xdebug.mode=coverage' vendor/bin/phpunit -c phpunit.xml --coverage-text=tests/output/coverage.txt && cat tests/output/coverage.txt
    PHPUnit 9.6.19 by Sebastian Bergmann and contributors.
    
    ......                                                              6 / 6 (100%)
    
    Time: 00:00.146, Memory: 6.00 MB
    
    OK (6 tests, 40 assertions)
    
    
    Code Coverage Report:
    2024-04-30 09:16:24
    
    Summary:
    Classes: 100.00% (7/7)
    Methods: 100.00% (17/17)
    Lines:   100.00% (115/115)
    
    Campaign
    Methods: 100.00% ( 2/ 2)   Lines: 100.00% (  2/  2)
    CampaignDataSource
    Methods: 100.00% ( 2/ 2)   Lines: 100.00% ( 13/ 13)
    Event
    Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
    EventsDataSource
    Methods: 100.00% ( 2/ 2)   Lines: 100.00% ( 13/ 13)
    Notifier
    Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  2/  2)
    OptimizationJob
    Methods: 100.00% ( 8/ 8)   Lines: 100.00% ( 83/ 83)
    OptimizationProps
    Methods: 100.00% ( 1/ 1)   Lines: 100.00% (  1/  1)
* ```
  
## Optimizations
Here some of the applied optimizations listed and partially explained 
* generators are more memory efficient when working with big data sets
* strict typing helps garbage collector to solve references and free up memory more efficiently. 
  Also it helps to avoid bugs.
* TTL of variables, objects should be as small as possible. Resources must be quickly freed or reused
* combining all files into one can help with loading all necessary data faster. Autoloader may slow up script runs. 
  For the given task tests show it does not have substantial speedup though. It is due to small number of files required.
* Redis serves here the main factor for efficient usage of resources. It has optimized data handling solutions, 
  transactions and easy to use. The lower number of operations and closer DB to PHP scripts the faster it will be.
* SPL library and native optimized functions give great increase in speed. `SplFixedArray`, iterators and other 
  components help to minimize memory and balance out speed of data processing.
* by avoiding usage of getter-setter methods, use of `readonly` props it is possible to minimize processing time 
  somewhat without having any negative impact on usability of entity classes.
* batch processing of event counters lessens number requests to Redis DB using pipelines.

## Notes
* `ratioThreshold` was interpreted as `measuredEvent / sourceEvent`. It provides more clear understanding with usage. 
* Some additional work was added to improve testing, performance for the script. So not only a place with placeholder 
  `// START HERE` was changed but data providers, entities also.
* Classes `EventsDataSource`, `CampaignDataSource` are lightly optimized to ease the writing tests 
  and not fail for large data sets. CSV files should not be used in production. It is done only for 
  convenience of testing.
* It is recommended to reconfigure PHP.ini script in production for better performance. 
  Given settings are used for testing mostly. Basic recommendations
  * update settings OpCache CLI: enable, memory, preload scripts to OpCache on startup etc.
  * update JIT VM usage and check if enabling it will boost performance for the given tasks
  * remove unused for production extensions - XDebug etc.
  * put PHP on host system instead of Docker
  * garbage collector settings may be adjusted
* Redis DB should be put on host system or closer without Docker and similar wrappers. Optimize configs to improve 
  performance, durability, availability etc.
* `Notifier` class was added for testing purposes to check sending email and proper handling blacklisting publishers.

## Performance
The script was run under Docker with MacOS M1 system with data set of: 
events - 1 million records, campaigns - 1000 records (see `output/*.csv` files). 
It took around 60 seconds and 5MB of RAM to complete processing.

```shell
$ docker exec app composer run app:run
> @php bin/run_optimization_job
Processing job took 44 seconds
Memory usage: 2MB
Memory peak usage: 2MB

$ docker compose exec redis redis-cli INFO memory
# Memory
used_memory:2993760
used_memory_human:2.86M
```
