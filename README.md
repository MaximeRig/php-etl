# PHP ETL Chain

[![Build Status](https://travis-ci.org/oliverde8/php-etl.svg?branch=master)](https://travis-ci.org/oliverde8/php-etl)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/oliverde8/php-etl/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/oliverde8/php-etl/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/oliverde8/php-etl/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/oliverde8/php-etl/?branch=master)
[![Donate](https://img.shields.io/badge/paypal-donate-yellow.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=oliverde8@gmail.com&lc=US&item_name=php-etl&no_note=0&cn=&curency_code=EUR&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted)
[![Latest Stable Version](https://poser.pugx.org/oliverde8/php-etl/v/stable)](https://packagist.org/packages/oliverde8/php-etl)
[![Latest Unstable Version](https://poser.pugx.org/oliverde8/php-etl/v/unstable)](https://packagist.org/packages/oliverde8/php-etl)
[![License](https://poser.pugx.org/oliverde8/php-etl/license)](https://packagist.org/packages/oliverde8/php-etl)

Php etl is a ETL that will allow transformation of data in a simple and efficient way.

It comes in 2 components : 

## Rule engine

The rule engine allows to have configuration based transformations to transform a particular data. 
This is integrated inside the ETL with the `RuleTransformOperation`. 

The rule engine can be used in standalone, [see docs](docs/RuleEngine.md)

## Understanding the ETL Chain 

An ETL chain is described by **chain operations** and **Items**. The **chain operation** holds the logic, this
means it can:
- Extract data into the item, (possibly duplicate the item)
- Transform the ite, 
- Load the item somewhere. 

Chain operations consumes **items** and order to create new **items**. 

There are 3 main item types: 
- **Data Item :** Is the most common item type. There is no special processes for these items. 

- **GroupedItems :** Is a multitude of data items, these are automatically split by the ETL. So if an
Chain operation returns a grouped item containing 2 data items, the next operation will be called twice, 
once for each item. 

- **ChainBreakItem :** Can be used by any Chain Operation to stop the flow in the chain. The next
operation will not be used as the chain has been broken. 

- **StopItem :** Is received by Chain Operation when all data items has been consumed. If the chain
operation receiving StopItem returns another kind of item the chain will continue to run until 
the chain operation sends a Stop Item. 

### Execution Examples

This is the simplest case. The chains receive an iterator containing 2 items in input, both items
are processed by each chain operation. This could be for example a list of customer. Each operation
changes the items.

![](docs/flow-1.png)

In the following example the iterator sends a single item. The first operation will then send **GroupedItems** 
containing 2 items. The first item could be a customer, and then we fetch each order of the customer
in the operation1.

![](docs/flow-2.png)

We can also group items, to make aggregations. The chain receives an iterator containg 2 items, 
the first operation processes both items. It breaks the chain for the first item, and returns an aggregation
of item1 & item 2. This can be used to count the number of customers.

![](docs/flow-3.png)

Chains can also be split, this would allow 2 different operations to be executed on the same item.

![](docs/flow-4.png)


## Creating a chain. 

There are a few operations pre-coded in the library. You can create you own. The entry of an ETL chain is 
a standard php `\Iterator`. 

There are 2 ways of writing a chain, either you code it; or you describe the chain in a yaml file. 

The fallowing 2 examples does take the same input and returns the same output. The only thing that changes is the
way it's done.

First you need to initialize all the objects. 
If you are using symfony check the [symfony bundle](https://github.com/oliverde8/phpEtlBundle). 

```php
<?php
$ruleApplier = new \Oliverde8\Component\RuleEngine\RuleApplier(
    new \Psr\Log\NullLogger(),
    [
        new \Oliverde8\Component\RuleEngine\Rules\Get(new \Psr\Log\NullLogger()),
        new \Oliverde8\Component\RuleEngine\Rules\Implode(new \Psr\Log\NullLogger()),
        new \Oliverde8\Component\RuleEngine\Rules\StrToLower(new \Psr\Log\NullLogger()),
        new \Oliverde8\Component\RuleEngine\Rules\StrToUpper(new \Psr\Log\NullLogger()),
        new \Oliverde8\Component\RuleEngine\Rules\ExpressionLanguage(new \Psr\Log\NullLogger()),
    ]
);


$builder = new \Oliverde8\Component\PhpEtl\ChainBuilder(new \Oliverde8\Component\PhpEtl\ExecutionContextFactory(new \Oliverde8\Component\PhpEtl\Model\File\LocalFileSystem()));
$builder->registerFactory(new RuleTransformFactory('rule-engine-transformer', RuleTransformOperation::class, $ruleApplier));
$builder->registerFactory(new SimpleGroupingFactory('simple-grouping', SimpleGroupingOperation::class));
$builder->registerFactory(new CsvFileWriterFactory('csv-write', FileWriterOperation::class));
```

### Coding an ETL Chain

You can see find here more information [here](docs/ChainCoded.md).

### Configuring a ETL Chain

Let's create a yml file describing the chain instead of wiring all the code we wrote above.

```yaml
transform1:
  operation: rule-engine-transformer
  options:
    add: true
    columns:
      uid:
        - implode:
            values:
              - 'PREFIX'
              - [{get : {field: 'ID_PART1'}}]
              - [{get : {field: "ID_PART2"}}]
            with: "_"

group:
  operation: simple-grouping
  options:
    grouping-key: [sku]
    group-identifier: [locale]

transform2:
  operation: rule-engine-transformer
  options:
    add: false
    columns:
        uid: # Fetching the uid of the first element!
            - get : {field: [0, 'uid']}
            
        label: # Fetching te label from the first if available and if not the second item.
            - get : {field: [0, 'label']}
            - get : {field: [1, 'label']}

write:
  operation: csv-write
  options:
    file: exemples/output.csv
```

Now we can run this : 

```php
<?php
$builder = new ChainBuilder();
$chainProcessor = $builder->buildChainProcessor(Yaml::parse(file_get_contents(__DIR__ . '/exemples/etl_chain.yml')));
$context = [];

$chainProcessor->process($inputIterator, $context);
```

As you can see we have simply used the chain builder instead of creating each operation manually.

## Creating you own operations. 

To create your own operation you need to extend `Oliverde8\Component\PhpEtl\ChainOperation\AbstractChainOperation`

In your class you will need to create a method taking in parameter an `ItemInterface` and a `ExecutionContext`

**Example**
```php
class MyOperation extends Oliverde8\Component\PhpEtl\ChainOperation\AbstractChainOperation
{
    protected function processItem(\Oliverde8\Component\PhpEtl\Item\ItemInterface $item, \Oliverde8\Component\PhpEtl\Model\ExecutionContext $executionContext): \Oliverde8\Component\PhpEtl\Item\ItemInterface
    {
        // TODO
        return $item;
    }

}
```

If you wish your operation to only process certain item types, for example data items, you can change the signature 
of your `processItem` method.
```php
class MyOperation extends Oliverde8\Component\PhpEtl\ChainOperation\AbstractChainOperation
{
    protected function processItem(\Oliverde8\Component\PhpEtl\Item\DataItemInterface $item, \Oliverde8\Component\PhpEtl\Model\ExecutionContext $executionContext): \Oliverde8\Component\PhpEtl\Item\ItemInterface
    {
        // TODO
        return $item;
    }

}
```

# FAQ

* **Why isn't there a database extract ?**
PHP-Etl is a library, not meant to be used standalone, it is meant to be used inside symfony, or magento projects. 
Each of these framework/cms's have their own way of handling the database. I do plan to make a Symfony bundle at one 
point which could have doctrine extractor. 

* **Is there a validation of the chain configuration ?**
Yes there is. you will get an error like this : 
```
There was an error building the operation 'simple-grouping' : 
 - "grouping-key" : This field is missing.
 - "file" : This field was not expected.
```

# TODO

* ChainBuilder
    * Write unit tests.
    
* Create a few more generic rules. 
    * Data formatting 
    * Numeric formatter
    * ...
    
* Improve docs.