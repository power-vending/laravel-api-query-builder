# Laravel JSON Query Builder

## Índice

1. [O que é este pacote](#o-que-é-este-pacote)
2. [Como funciona](#como-funciona)
3. [Requisitos do sistema](#requisitos-do-sistema)
4. [Instalação](#instalação)
5. [Configuração inicial](#configuração-inicial)
6. [Rota de schema](#rota-de-schema)
7. [Operadores de busca](#operadores-de-busca)
8. [Uso básico](#uso-básico)
9. [Parâmetros disponíveis](#parâmetros-disponíveis)
10. [Trabalhando com relacionamentos](#trabalhando-com-relacionamentos)
    - [Sub-search nested (busca aninhada)](#sub-search-nested-busca-aninhada)
    - [Falsos positivos na notação com ponto](#falsos-positivos-na-notação-com-ponto)
    - [Guia de decisão: notação com ponto vs. sub-search nested](#guia-de-decisão-notação-com-ponto-vs-sub-search-nested)
11. [Exemplos práticos completos](#exemplos-práticos-completos)
12. [Customizações avançadas](#customizações-avançadas)
    - [Sistema de tipos (`types`)](#sistema-de-tipos-types)
    - [Operadores por cast (`cast_operators`)](#operadores-por-cast-cast_operators)
    - [Como `types` e `cast_operators` trabalham juntos](#como-types-e-cast_operators-trabalham-juntos)
13. [Erros retornados pela API](#erros-retornados-pela-api)
14. [Solução de problemas](#solução-de-problemas)
15. [Testes](#testes)

---

## O que é este pacote

Este pacote Laravel permite que você construa consultas (queries) dinâmicas no banco de dados usando parâmetros JSON através de requisições HTTP. Em vez de criar manualmente cada filtro, ordenação e paginação em seus controllers, este pacote processa automaticamente os parâmetros enviados pelo frontend.

### Para que serve

Imagine que você tem uma API REST que retorna uma lista de produtos. Sem este pacote, você precisaria criar código para cada tipo de filtro possível:

```php
// Sem o pacote - você precisa tratar cada caso manualmente
if ($request->has('name')) {
    $query->where('name', 'like', '%' . $request->name . '%');
}
if ($request->has('min_price')) {
    $query->where('price', '>=', $request->min_price);
}
if ($request->has('category')) {
    $query->where('category_id', $request->category);
}
```

Com este pacote, o frontend envia um JSON estruturado e tudo é processado automaticamente:

```php
// Com o pacote - uma linha resolve tudo
return Product::query()->requestPaginate();
```

### Origem

Este pacote foi originalmente desenvolvido por Neal Arec (nealarec/laravel-api-query-builder) e foi internalizado pela Power Vending para permitir customizações específicas e manutenção independente nos projetos internos da empresa.

---

## Como funciona

O pacote funciona interpretando parâmetros JSON enviados via query string (GET) ou body (POST) de requisições HTTP e transformando-os em queries Eloquent do Laravel.

### Fluxo de funcionamento

1. O frontend faz uma requisição HTTP com parâmetros JSON
2. O pacote intercepta esses parâmetros
3. Valida e processa cada parâmetro (filtros, ordenação, paginação, etc)
4. Constrói a query Eloquent correspondente
5. Retorna os dados de acordo com as especificações

### Exemplo visual

```
Frontend envia:
GET /api/products?search={"name":"LIKE:Notebook","price":"GT:1000"}&order_by={"price":"asc"}

Pacote transforma em:
SELECT * FROM products 
WHERE name LIKE '%Notebook%' 
  AND price > 1000 
ORDER BY price ASC

Retorna:
{
  "data": [...],
  "current_page": 1,
  "total": 42
}
```

---

## Requisitos do sistema

Para usar este pacote, você precisa ter as seguintes versões instaladas:

### PHP

- Versão 8.0 ou superior
- Extensões necessárias: PDO, Mbstring

### Laravel

O pacote é compatível com múltiplas versões do Laravel:

- Laravel 8.x
- Laravel 9.x
- Laravel 10.x
- Laravel 11.x
- Laravel 12.x

### Doctrine DBAL

- Versão 3.0 ou superior
- Versão 4.0 também suportada

O Doctrine DBAL é necessário para algumas operações de análise de schema do banco de dados.

### Composer

Necessário para gerenciar as dependências do PHP.

---

## Instalação

### Instalação via Composer

Instale o pacote usando o Composer:

```bash
composer require power-vending/api-query-builder
```

O pacote será instalado automaticamente e o Laravel irá registrar o service provider.

---

## Configuração inicial

### Passo 1: Publicar arquivos de configuração

Publique o arquivo de configuração do pacote para o seu projeto:

```bash
php artisan vendor:publish --tag=api-query-builder-config
```

Este comando irá criar o arquivo `config/api-query-builder.php` no diretório de configurações do seu projeto.

### Passo 2: Entender o arquivo de configuração

Abra o arquivo `config/api-query-builder.php`. Você verá algo parecido com:

```php
return [
    // Operadores de busca disponíveis
    'operators' => [
        // Lista de classes de operadores
    ],
    
    // Colunas que nunca podem ser acessadas via query
    'global_forbidden_columns' => [
        'password',
        'remember_token',
    ],

    // Relacionamentos que nunca podem ser carregados ou expostos por schema
    'global_forbidden_relations' => [
        'internalRelation',
        'company.users',
    ],
    
    // Outras configurações...
];
```

### Passo 3: Adicionar a trait ao Model

Para que um Model aceite os parâmetros do pacote, adicione a trait `ApiQueryBuilder`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class Product extends Model
{
    use ApiQueryBuilder;
}
```

A trait disponibiliza os métodos `requestQuery()` e `requestPaginate()` no Model. Sem ela, o pacote não tem efeito sobre o Model.

### Passo 4: Configurar colunas proibidas (Segurança)

É muito importante configurar quais colunas não devem ser acessíveis via query para proteger dados sensíveis:

```php
'global_forbidden_columns' => [
    'password',           // Senhas nunca devem ser retornadas
    'remember_token',     // Tokens de sessão
    'api_token',          // Tokens de API
    'secret_key',         // Chaves secretas
    'credit_card',        // Dados bancários
    'cpf',                // Dados pessoais sensíveis
],
```

Qualquer tentativa de acessar essas colunas será bloqueada automaticamente.

**Também é possível definir colunas proibidas diretamente nos Models**, o que é útil para proteger campos específicos de cada tabela:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class User extends Model
{
    use ApiQueryBuilder;
    
    /**
     * Colunas que não podem ser acessadas via API query
     */
    protected $apiQueryBuilderForbiddenColumns = [
        'password',
        'remember_token',
        'two_factor_secret',
        'api_token',
    ];
}
```

**Ordem de precedência das colunas proibidas:**
1. `global_forbidden_columns` (config) - aplica a todos os models
2. `$apiQueryBuilderForbiddenColumns` (model) - específico da model
3. `model_options[Model::class]['forbidden_columns']` (config) - sobrescreve tudo

**IMPORTANTE:** As três fontes são mescladas (união). Se quiser usar apenas config, não defina `$apiQueryBuilderForbiddenColumns` na model.

### Configurando relacionamentos proibidos

Assim como colunas, você pode bloquear relacionamentos específicos para evitar que o schema os auto-descubra ou que o frontend peça carregamento deles.

```php
'global_forbidden_relations' => [
    'internalRelation',
    'company.users',
],
```

Também é possível restringir relacionamentos por model via `model_options`:

```php
'model_options' => [
    \App\Models\User::class => [
        'forbidden_relations' => ['profile', 'company'],
    ],
],
```

Além da configuração, você também pode declarar a propriedade `$apiQueryBuilderForbiddenRelations` diretamente no model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $apiQueryBuilderForbiddenRelations = [
        'profile',
        'company.users',
    ];
}
```

As três fontes são mescladas (união): `global_forbidden_relations`, `model_options[Model::class]['forbidden_relations']` e `$apiQueryBuilderForbiddenRelations`.

Relacionamentos proibidos são tratados como inexistentes: o schema não os carrega automaticamente e qualquer tentativa de requestar esses relacionamentos gera erro de relation not found.

### Passo 5: Configurar estrutura de rotas do pacote

O pacote expõe suas rotas por meio da chave `routes` na configuração.

No projeto consumidor, configure diretamente no arquivo `config/api-query-builder.php`:

```php
<?php

use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;

return [
    'resource_models' => [
        'products' => \App\Models\Product::class,
        'product-categories' => \App\Models\Category::class,
    ],

    'routes' => [
        'engineering.api.v1.api_query_builder.schema.show' => [
            'method' => 'get',
            'uri' => 'eng-api/v1/api-query-builder/{resource}/schema',
            'action' => [SchemaController::class, 'show'],
            'middlewares' => ['api', 'auth:sanctum', 'acl'],
        ],
    ],
];
```

Se você não quiser customizar o path, pode manter a rota padrão:

```php
'routes' => [
    'api.query_builder.schema.show' => [
        'method' => 'get',
        'uri' => 'api-query-builder/{resource}/schema',
        'action' => [\PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController::class, 'show'],
        'middlewares' => ['api'],
    ],
],
```

Estrutura esperada:

```php
'routes' => [
        'nome.completo.da.rota' => [
                'method' => 'get',
                'uri' => 'api-query-builder/{resource}/schema',
                'action' => [\PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController::class, 'show'],
                'middlewares' => ['api'],
        ],
],
```

Campos da rota:

1. `method`: verbo HTTP (`get`, `post`, `put`, `patch`, `delete`, `options`)
2. `uri`: caminho completo da rota
3. `action`: array com `[Controller::class, 'method']`
4. `middlewares`: lista de middlewares por rota

Exemplo de override no projeto consumidor:

```php
'routes' => [
        'engineering.api.v1.api_query_builder.schema.show' => [
                'method' => 'get',
                'uri' => 'eng-api/v1/api-query-builder/{resource}/schema',
                'action' => [\PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController::class, 'show'],
                'middlewares' => ['api', 'auth:sanctum', 'acl'],
        ],
],
```

Observação importante:

1. A rota só é registrada quando a `action` aponta para controller/método válidos do namespace do pacote
2. Configurações inválidas de `action` são ignoradas para evitar exposição indevida

---

## Rota de schema

A rota de schema retorna metadados para construção de filtros no frontend, incluindo:

1. model e table
2. searchable_columns (tipo, operadores e nullable)
3. sortable_columns
4. relations (árvore aninhada)

Rota padrão:

```http
GET /api-query-builder/{resource}/schema
```

Parâmetros aceitos:

1. `resource` (path): chave definida em `api-query-builder.resource_models`
2. `relations[]` (query, opcional): lista de relações extras a serem mescladas ao retorno padrão

Exemplos de chamada:

```http
GET /api-query-builder/products/schema
GET /api-query-builder/products/schema?relations[]=category
GET /api-query-builder/products/schema?relations[]=category.parent
```

Comportamento de `relations`:

1. Sem `relations`: **auto-descobre e carrega automaticamente todas as relações públicas do modelo**
2. `relations[]` informado: adiciona essas relações ao resultado (mescla com as auto-descobertas)
3. Para cada caminho enviado em `relations[]`, o schema carrega **somente o primeiro nível** de cada nó do caminho
4. Relação inexistente: lança erro de validação de relação (tratável pelo Handler da aplicação)

Exemplo:

1. `GET /api-query-builder/terminals/schema` retorna **automaticamente** todas as relações públicas do model `Terminal`
2. `GET /api-query-builder/terminals/schema?relations[]=company` retorna as relações de primeiro nível de `Terminal` + primeiro nível de `company` (ex.: `company.address`, `company.users`)
3. `GET /api-query-builder/terminals/schema?relations[]=company.users` retorna as relações de primeiro nível de `Terminal` + primeiro nível de `company` + primeiro nível de `company.users` (ex.: `company.users.profile`)

Exemplo resumido de resposta:

```json
{
    "model": "App\\Models\\Product",
    "table": "products",
    "searchable_columns": {
        "name": {
            "type": "varchar(191)",
            "operators": ["STARTS_WITH", "ENDS_WITH", "LIKE", "NE", "EQ"],
            "nullable": true
        }
    },
    "sortable_columns": ["id", "name"],
    "relations": {
        "category": {
            "model": "App\\Models\\Category",
            "table": "categories",
            "relations": {
                "parent": {
                    "model": "App\\Models\\Category",
                    "table": "categories",
                    "relations": []
                }
            }
        }
    }
}
```

### Relacionamentos polimórficos

Relacionamentos polimórficos criados com `morphTo()` não definem um modelo de destino fixo, o que impede o schema de resolver corretamente os campos e relacionamentos do lado polimórfico.

```php
// Exemplo típico — o schema NÃO consegue introspeccionar o modelo de destino
public function requester(): MorphTo
{
    return $this->morphTo();
}
```

Para que o schema consiga carregar os metadados corretamente, é recomendado (opcional) criar relacionamentos auxiliares tipados com `belongsTo`, um por cada modelo de destino possível. Cada relacionamento deve filtrar pelo campo `*_type` correspondente:

```php
// Relacionamentos auxiliares tipados — o schema resolve cada modelo corretamente
public function requesterUser(): BelongsTo
{
    return $this->belongsTo(User::class, 'requester_id')
        ->where('requester_type', User::class);
}

public function requesterOperator(): BelongsTo
{
    return $this->belongsTo(Operator::class, 'requester_id')
        ->where('requester_type', Operator::class);
}
```

Com isso, ao solicitar o schema, você pode referenciar cada variação diretamente:

```http
GET /api-query-builder/tickets/schema?relations[]=requester_user&relations[]=requester_operator
```

E o retorno trará os campos de cada modelo destino de forma independente:

```json
{
    "relations": {
        "requester_user": {
            "model": "App\\Models\\User",
            "table": "users",
            "relations": {}
        },
        "requester_operator": {
            "model": "App\\Models\\Operator",
            "table": "operators",
            "relations": {}
        }
    }
}
```

> **Nota:** O relacionamento `morphTo()` original pode ser mantido no model normalmente — os relacionamentos auxiliares são apenas para uso com o schema e não interferem no comportamento padrão do Eloquent.

---

## Operadores de busca

Antes de começar a usar o pacote, é fundamental entender os **operadores** disponíveis. Os operadores definem como a comparação será feita nos filtros (igual a, maior que, contém, etc).

### Sintaxe geral

```
"coluna": "OPERADOR:valor"
```

Para múltiplos valores, use `;` (ponto e vírgula) como separador:

```
"coluna": "OPERADOR:valor1;valor2;valor3"
```

### Comportamento de EQ e NE em colunas string

Os operadores `EQ:` e `NE:` têm **comportamento condicional** que depende do tipo da coluna resolvido via introspecção de schema e da quantidade de valores:

| Situação | SQL gerado |
|---|---|
| Tipo resolvido como `string` + valor único (sem wildcards) | `LIKE` / `NOT LIKE` |
| Qualquer outro caso (múltiplos valores, tipo `generic`, tipo numérico, relação aninhada) | `IN` / `NOT IN` |

O tipo da coluna é determinado em tempo de execução consultando o schema do banco. Se a coluna não for encontrada no schema do model (por exemplo, em buscas dentro de relações aninhadas ou quando a introspecção falha), o tipo cai para `generic` e o comportamento passa a ser sempre `IN`.

**Exemplo com tipo resolvido como texto (string, varchar, text, etc.):**
```json
{"name": "EQ:Notebook Dell"}      -> WHERE name LIKE 'Notebook Dell'    (1 valor, tipo texto - match exato)
{"name": "EQ:%Dell%"}              -> WHERE name LIKE '%Dell%'           (1 valor com wildcard - contém)
{"name": "EQ:Dell;HP"}             -> WHERE name IN ('Dell', 'HP')       (múltiplos valores)
```

**Exemplo com tipo numérico ou genérico:**
```json
{"category_id": "EQ:1"}            -> WHERE category_id IN (1)
```

> **Comportamento do `EQ:` com um único valor de texto:** Agora detecta corretamente colunas do tipo `varchar`, `text`, `char`, etc. (além de `string`) e usa `LIKE` em vez de `IN`. Para busca parcial (contém), use o micro-operador `%` nos valores ou prefira os operadores `LIKE:`, `STARTS_WITH:` ou `ENDS_WITH:`.

> **PostgreSQL:** Usa `ILIKE` em vez de `LIKE` nas situações em que LIKE é gerado.

### Lista completa de operadores

#### EQ - Equals (IN / LIKE condicional)

**Comportamento:**
- **Colunas de texto** (varchar, text, char, string, etc.) com **um único valor**: usa `LIKE`
- **Colunas de texto** com **múltiplos valores**: usa `IN`
- **Colunas numéricas** ou outros tipos: sempre usa `IN`

```json
{"category_id": "EQ:1"}               -> WHERE category_id IN (1)
{"category_id": "EQ:1;2;3"}           -> WHERE category_id IN (1, 2, 3)
{"name": "EQ:Notebook;Mouse"}         -> WHERE name IN ('Notebook', 'Mouse')
{"name": "EQ:Notebook Dell"}          -> WHERE name LIKE 'Notebook Dell'         (match exato)
{"name": "EQ:%Dell%"}                 -> WHERE name LIKE '%Dell%'                (contém)
{"name": "EQ:Notebook%"}              -> WHERE name LIKE 'Notebook%'             (começa com)
{"name": "EQ:%Gamer"}                 -> WHERE name LIKE '%Gamer'                (termina com)
```

> **Dica:** Para buscas textuais com wildcards automáticos, use `LIKE:` (adiciona `%` em ambos os lados), `STARTS_WITH:` ou `ENDS_WITH:`.

#### NE - Not Equals (NOT IN / NOT LIKE condicional)

Inverso do `EQ:`. Segue as mesmas regras de detecção de tipo.

**Comportamento:**
- **Colunas de texto** (varchar, text, char, string, etc.) com **um único valor**: usa `NOT LIKE`
- **Colunas de texto** com **múltiplos valores**: usa `NOT IN`
- **Colunas numéricas** ou outros tipos: sempre usa `NOT IN`

```json
{"category_id": "NE:3"}               -> WHERE category_id NOT IN (3)
{"category_id": "NE:3;4"}             -> WHERE category_id NOT IN (3, 4)
{"name": "NE:Usado;Defeito"}          -> WHERE name NOT IN ('Usado', 'Defeito')
{"name": "NE:Notebook Dell"}          -> WHERE name NOT LIKE 'Notebook Dell'
{"name": "NE:%teste%"}                -> WHERE name NOT LIKE '%teste%'
```

#### GT - Greater Than (Maior que)

Aceita **exatamente um valor**.

```json
{"price": "GT:1000"}                  -> WHERE price > 1000
{"stock": "GT:0"}                     -> WHERE stock > 0
{"discount": "GT:10"}                 -> WHERE discount > 10
```

#### GE - Greater than or Equal (Maior ou igual a)

Aceita **exatamente um valor**.

```json
{"price": "GE:1000"}                  -> WHERE price >= 1000
{"stock": "GE:5"}                     -> WHERE stock >= 5
```

#### LT - Less Than (Menor que)

Aceita **exatamente um valor**.

```json
{"price": "LT:100"}                   -> WHERE price < 100
{"discount": "LT:50"}                 -> WHERE discount < 50
```

#### LE - Less than or Equal (Menor ou igual a)

Aceita **exatamente um valor**.

```json
{"price": "LE:100"}                   -> WHERE price <= 100
{"discount": "LE:20"}                 -> WHERE discount <= 20
```

#### BT - Between (Entre dois valores)

Aceita **exatamente dois valores** separados por `;`.

```json
{"price": "BT:100;500"}                       -> WHERE price BETWEEN 100 AND 500
{"stock": "BT:10;100"}                        -> WHERE stock BETWEEN 10 AND 100
{"created_at": "BT:2024-01-01;2024-12-31"}   -> WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'
```

#### NB - Not Between (Não está entre)

Inverso do `BT:`. Aceita **exatamente dois valores** separados por `;`.

```json
{"price": "NB:100;500"}               -> WHERE price NOT BETWEEN 100 AND 500
{"discount": "NB:0;5"}                -> WHERE discount NOT BETWEEN 0 AND 5
```

#### LIKE - Like (Contém)

Busca o valor em qualquer posição do texto. O `%` é adicionado automaticamente em ambos os lados.

```json
{"name": "LIKE:Dell"}                 -> WHERE name LIKE '%Dell%'
{"description": "LIKE:gamer"}         -> WHERE description LIKE '%gamer%'
```

**Nota:** Diferente do `EQ:`, o operador `LIKE:` sempre usa `LIKE`, independente da quantidade de valores ou tipo da coluna.

#### STARTS_WITH - Starts With (Começa com)

```json
{"name": "STARTS_WITH:Notebook"}      -> WHERE name LIKE 'Notebook%'
{"sku": "STARTS_WITH:PRD"}            -> WHERE sku LIKE 'PRD%'
{"code": "STARTS_WITH:DEV"}           -> WHERE code LIKE 'DEV%'
```

#### ENDS_WITH - Ends With (Termina com)

```json
{"name": "ENDS_WITH:Gamer"}           -> WHERE name LIKE '%Gamer'
{"filename": "ENDS_WITH:.pdf"}        -> WHERE filename LIKE '%.pdf'
```

### Micro-operadores

Micro-operadores são prefixos aplicados **diretamente em cada valor** (após o operador principal) para modificar o comportamento de busca individualmente. Funcionam com `EQ:` e `NE:`.

#### `!` — Negação de valor individual

Nega somente aquele valor dentro de uma lista. Combina IN e NOT IN na mesma query.

```json
{"status": "EQ:active;!deleted;!archived"}
```

```sql
WHERE status IN ('active') AND status NOT IN ('deleted', 'archived')
```

> **Diferença entre `NE:` e `!`:**
> - `NE:val1;val2` — nega **toda a lista**: `NOT IN ('val1', 'val2')`
> - `EQ:val1;!val2` — val1 vai para IN, val2 vai para NOT IN

#### `%` — Wildcard (LIKE parcial)

Adiciona busca LIKE quando colocado no início, no fim, ou em ambas as extremidades do valor.

```json
{"name": "EQ:Notebook%;%Mouse;%Gamer%"}
```

```sql
WHERE name LIKE 'Notebook%' AND name LIKE '%Mouse' AND name LIKE '%Gamer%'
```

Funciona com negação também:

```json
{"name": "EQ:!%usado%"}    -> WHERE name NOT LIKE '%usado%'
```

#### `null` e `!null` — Verificação de nulo

```json
{"discount": "EQ:null"}     -> WHERE discount IS NULL
{"discount": "EQ:!null"}    -> WHERE discount IS NOT NULL
```

### Operadores lógicos por coluna (`&&` e `||`)

Permitem combinar múltiplas condições de operadores **para uma mesma coluna** usando AND ou OR. A sintaxe é colocar o operador lógico entre dois pares `OPERADOR:valor`.

```json
{"id": "EQ:1||EQ:2"}
```

```sql
WHERE id IN (1) OR id IN (2)
```

```json
{"name": "EQ:Notebook%&&EQ:%Dell"}
```

```sql
WHERE name LIKE 'Notebook%' AND name LIKE '%Dell'
```

> **Atenção:** A precedência segue a lógica booleana padrão: AND tem prioridade sobre OR. Portanto `x&&y||z&&q` equivale a `(x AND y) OR (z AND q)`.

### Operadores lógicos como chave (`&&` e `||` top-level)

Além de usar `&&` e `||` dentro do valor de uma coluna, você também pode usá-los como **chaves** dentro do objeto `search` para agrupar condições de **colunas diferentes** com AND ou OR.

#### `&&` como chave — AND entre grupos de colunas

```json
{
    "search": {
        "&&": [
            {"status": "EQ:active"},
            {"price": "GT:100"}
        ]
    }
}
```

```sql
WHERE (status IN ('active') AND price > 100)
```

#### `||` como chave — OR entre grupos de colunas

```json
{
    "search": {
        "||": [
            {"status": "EQ:active"},
            {"status": "EQ:pending"}
        ]
    }
}
```

```sql
WHERE (status IN ('active') OR status IN ('pending'))
```

#### Combinando `||` e `&&` com condições normais

```json
{
    "search": {
        "category_id": "EQ:5",
        "||": [
            {"status": "EQ:active"},
            {"status": "EQ:pending"}
        ]
    }
}
```

```sql
WHERE category_id IN (5)
  AND (status IN ('active') OR status IN ('pending'))
```

#### Exemplo avançado: agrupamento aninhado

```json
{
    "search": {
        "&&": [
            {"price": "GT:100"},
            {
                "||": [
                    {"status": "EQ:active"},
                    {"status": "EQ:featured"}
                ]
            }
        ]
    }
}
```

```sql
WHERE (
    price > 100
    AND (status IN ('active') OR status IN ('featured'))
)
```

> **Diferença entre os dois usos:**
> - **Dentro do valor** (`"name": "EQ:A||EQ:B"`) — aplica OR/AND sobre a **mesma coluna**
> - **Como chave** (`"||": [...]`) — aplica OR/AND sobre **grupos de colunas distintas**

### IMPORTANTE - Ordem dos operadores na configuração

A ordem dos operadores no arquivo `config/api-query-builder.php` é crítica. **Operadores com mais caracteres devem vir antes** dos com menos caracteres, pois o parser busca o primeiro que encontrar na string.

```php
'operators' => [
    StartsWith::class,            // STARTS_WITH:   (13 chars)
    EndsWith::class,              // ENDS_WITH:      (10 chars)
    Like::class,                  // LIKE:           (5 chars)
    NotEquals::class,             // NE:             (3 chars)
    NotBetween::class,            // NB:             (3 chars)
    LessThanOrEqual::class,       // LE:             (3 chars)
    GreaterThanOrEqual::class,    // GE:             (3 chars)
    Between::class,               // BT:             (3 chars)
    Equals::class,                // EQ:             (3 chars)
    LessThan::class,              // LT:             (3 chars)
    GreaterThan::class,           // GT:             (3 chars)
],
```

**Não altere essa ordem** a menos que saiba exatamente o que está fazendo.

---

## Uso básico

### Passo 1: Adicionar o Trait ao Model

Para usar o pacote em um Model, adicione o trait `ApiQueryBuilder`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class Product extends Model
{
    use ApiQueryBuilder;
    
    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'stock',
    ];
}
```

O trait `ApiQueryBuilder` adiciona os métodos `requestQuery()` e `requestPaginate()` ao seu model, que processam automaticamente os parâmetros JSON das requisições.

### Passo 2: Usar no Controller

No seu controller, use o método `requestPaginate()` para processar automaticamente os parâmetros da requisição:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Método requestPaginate() - retorna resultados paginados
        return Product::query()->requestPaginate();
    }
    
    public function search(Request $request)
    {
        // Método requestQuery() - retorna o Builder para customizações
        $query = Product::query()->requestQuery();
        
        // Você pode adicionar condições extras ao Builder
        $query->where('company_id', auth()->user()->company_id);
        
        return $query->get();
    }
}
```

**Diferença entre os métodos:**
- `requestPaginate()`: Processa os parâmetros e retorna resultados paginados automaticamente
- `requestQuery()`: Processa os parâmetros e retorna o Builder para você adicionar mais condições

Pronto! Agora seu endpoint já aceita todos os parâmetros JSON do pacote.

### Passo 3: Fazer uma requisição do frontend

Do frontend, você pode fazer requisições como:

```javascript
// JavaScript - Fetch API
const params = {
    search: {
        name: "LIKE:Notebook",
        price: "GT:1000"
    },
    order_by: {
        price: "asc"
    },
    _per_page: 15,
    page: 1
};

const queryString = new URLSearchParams(params).toString();
const response = await fetch(`/api/products?${queryString}`);
const data = await response.json();
```

---

## Parâmetros disponíveis

Agora que você já conhece os operadores de busca, vamos explorar todos os parâmetros que você pode usar nas requisições JSON para construir queries dinâmicas.

### 1. search (Filtros)

Permite filtrar os dados usando os operadores que você acabou de aprender na seção anterior.

**Sintaxe:**

```json
{
    "search": {
        "coluna": "OPERADOR:valor"
    }
}
```

**Exemplo:**

```json
{
    "search": {
        "name": "LIKE:Notebook",
        "price": "GT:1000",
        "status": "EQ:active"
    }
}
```

**Query string:**

```
?search={"name":"LIKE:Notebook","price":"GT:1000","status":"EQ:active"}
```

### 2. relations (Carregar relacionamentos)

Carrega relacionamentos Eloquent junto com a entidade principal (eager loading).

#### Sintaxe básica (relacionamentos simples)

```json
{
    "relations": ["relacionamento1", "relacionamento2"]
}
```

**Exemplo:**

```json
{
    "relations": ["category", "manufacturer", "reviews"]
}
```

**Query string:**

```
?relations=["category","manufacturer","reviews"]
```

Isso irá executar algo como:

```php
Product::with(['category', 'manufacturer', 'reviews'])->get();
```

#### Sintaxe avançada (relacionamentos com configurações)

**Você pode passar configurações específicas para cada relacionamento**, permitindo filtrar, ordenar e selecionar campos dentro do relacionamento:

```json
{
    "relations": [
        "category",
        {
            "reviews": {
                "search": {
                    "rating": "GE:4"
                },
                "order_by": {
                    "created_at": "desc"
                },
                "returns": ["id", "rating", "comment", "created_at"]
            }
        }
    ]
}
```

**O que acontece:**
- `category`: carrega normalmente (simples)
- `reviews`: carrega apenas reviews com rating >= 4, ordenadas por data decrescente, retornando apenas as colunas especificadas

**Possibilidades dentro das configurações de relacionamento:**
- `search` - Filtrar registros do relacionamento
- `order_by` - Ordenar registros do relacionamento
- `returns` - Selecionar colunas específicas do relacionamento
- `limit` - Limitar quantidade de registros do relacionamento

#### Exemplo completo: Produtos com reviews filtradas

```json
{
    "search": {
        "price": "BT:100;1000",
        "status": "EQ:active"
    },
    "relations": [
        "category",
        {
            "reviews": {
                "search": {
                    "rating": "GE:4",
                    "verified": "EQ:1"
                },
                "order_by": {
                    "helpful_votes": "desc",
                    "created_at": "desc"
                }
            }
        },
        {
            "images": {
                "search": {
                    "is_primary": "EQ:0"
                },
                "order_by": {
                    "order": "asc"
                }
            }
        }
    ],
    "_per_page": 20
}
```

**Resultado:**
- Busca produtos entre 100 e 1000, status ativo
- Carrega categoria (todas)
- Carrega apenas reviews verificadas com rating >= 4, ordenadas por votos úteis
- Carrega apenas imagens que não são primárias, ordenadas por ordem

#### Casos de uso práticos

**1. Carregar últimos 5 pedidos de cada cliente:**

```json
{
    "relations": [
        {
            "orders": {
                "order_by": {"created_at": "desc"},
                "limit": 5
            }
        }
    ]
}
```

**2. Carregar comentários ativos, mais recentes primeiro:**

```json
{
    "relations": [
        {
            "comments": {
                "search": {"status": "EQ:approved"},
                "order_by": {"created_at": "desc"}
            }
        }
    ]
}
```

**3. Misturar relacionamentos simples e configurados:**

```json
{
    "relations": [
        "user",
        "category",
        {
            "tags": {
                "order_by": {"name": "asc"}
            }
        }
    ]
}
```

### 3. order_by (Ordenação)

Define a ordem dos resultados.

**Sintaxe:**

```json
{
    "order_by": {
        "coluna1": "asc",
        "coluna2": "desc"
    }
}
```

**Exemplo:**

```json
{
    "order_by": {
        "price": "asc",
        "created_at": "desc"
    }
}
```

**Query string:**

```
?order_by={"price":"asc","created_at":"desc"}
```

**Valores aceitos:**
- `asc` - ordem crescente (A-Z, 0-9, mais antigo para mais novo)
- `desc` - ordem decrescente (Z-A, 9-0, mais novo para mais antigo)

**Qualificação de colunas:**

Toda coluna do `order_by` é prefixada com a tabela antes de ir para o SQL, evitando erros de
coluna ambígua quando a query já tem `JOIN`s (por exemplo, um filtro por relacionamento seguido
de uma ordenação):

```json
{"order_by": {"name": "asc"}}
```

```sql
order by "products"."name" asc
```

Também é possível informar o prefixo manualmente. Se o prefixo for a tabela do model ou uma
tabela já presente em algum `JOIN` da query, ele é respeitado e nenhum `JOIN` extra é criado:

```json
{"order_by": {"products.name": "asc"}}
```

Caso o prefixo não seja uma tabela conhecida da query, ele é interpretado como um relacionamento
(veja [Ordenação por relacionamento](#ordenação-por-relacionamento)).

Quando a mesma tabela precisa ser incluída mais de uma vez — relacionamentos auto-referenciados
(`parent.name`) ou dois relacionamentos apontando para a mesma tabela (`created_by.name` e
`updated_by.name`) — o pacote gera um alias (`authors`, `authors_2`, ...) para cada `JOIN`:

```sql
left join "authors" on "tickets"."created_by" = "authors"."id"
left join "authors" as "authors_2" on "tickets"."updated_by" = "authors_2"."id"
order by "authors"."name" asc, "authors_2"."name" desc
```

### 4. limit e offset (Paginação manual)

Controla quantos registros retornar e a partir de qual posição.

**Sintaxe:**

```
?limit=10&offset=20
```

**Explicação:**
- `limit`: quantos registros retornar (máximo)
- `offset`: quantos registros pular antes de começar a retornar

**Exemplo:**

```
?limit=10&offset=0   -> Registros 1-10 (primeira página)
?limit=10&offset=10  -> Registros 11-20 (segunda página)
?limit=10&offset=20  -> Registros 21-30 (terceira página)
```

### 5. page e _per_page (Paginação automática)

Paginação estilo Laravel Paginator.

**Sintaxe:**

```
?page=1&_per_page=15
```

**Explicação:**
- `page`: número da página (começa em 1)
- `_per_page`: quantos registros por página

**Exemplo:**

```
?page=1&_per_page=20  -> Primeira página, 20 itens
?page=2&_per_page=20  -> Segunda página, 20 itens
```

**Resposta:**

```json
{
    "current_page": 1,
    "data": [...],
    "first_page_url": "http://api.com/products?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://api.com/products?page=5",
    "next_page_url": "http://api.com/products?page=2",
    "path": "http://api.com/products",
    "per_page": 20,
    "prev_page_url": null,
    "to": 20,
    "total": 100
}
```

### 6. returns (Selecionar colunas específicas)

Retorna apenas as colunas especificadas (SELECT específico).

**Sintaxe:**

```json
{
    "returns": ["coluna1", "coluna2", "coluna3"]
}
```

**Exemplo:**

```json
{
    "returns": ["id", "name", "price"]
}
```

**Query string:**

```
?returns=["id","name","price"]
```

Isso gera:

```sql
SELECT id, name, price FROM products
```

Em vez de:

```sql
SELECT * FROM products
```

**Benefícios:**
- Reduz o tráfego de rede
- Melhora a performance
- Protege dados sensíveis

### 7. excepts (Excluir colunas específicas)

Retorna todas as colunas EXCETO as especificadas.

**Sintaxe:**

```json
{
    "excepts": ["coluna1", "coluna2"]
}
```

**Exemplo:**

```json
{
    "excepts": ["password", "remember_token", "api_token"]
}
```

**Query string:**

```
?excepts=["password","remember_token","api_token"]
```

**Nota:** Não use `returns` e `excepts` ao mesmo tempo. Escolha um ou outro.

### 8. count (Adicionar contagem)

Adiciona `count(*) as count` ao SELECT. Útil para queries de agregação.

**Sintaxe:**

```
?count=true
```

**Exemplo:**

```json
{
    "search": {
        "status": "EQ:active"
    },
    "count": true
}
```

```
?search={"status":"EQ:active"}&count=true
```

### 9. group_by (Agrupamento)

Agrupa os resultados por uma ou mais colunas.

**Sintaxe:**

```json
{
    "group_by": ["coluna1", "coluna2"]
}
```

**Exemplo:**

```json
{
    "group_by": ["category_id", "status"]
}
```

**Query string:**

```
?group_by=["category_id","status"]
```

Isso gera:

```sql
GROUP BY category_id, status
```

### 10. soft_deleted (Incluir registros deletados)

Inclui registros com soft delete na query. Por padrão, o Eloquent exclui registros com `deleted_at` preenchido. Este parâmetro remove esse escopo.

**Sintaxe:**

```
?soft_deleted=true
```

Requer que o Model use a trait `SoftDeletes` do Laravel:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    use ApiQueryBuilder;
}
```

**Exemplo:**

```
?search={"name":"LIKE:Produto"}&soft_deleted=true
```

```sql
-- SEM soft_deleted=true
SELECT * FROM products WHERE deleted_at IS NULL AND name LIKE '%Produto%'

-- COM soft_deleted=true
SELECT * FROM products WHERE name LIKE '%Produto%'
```

### 11. doesnt_have_relations (Excluir registros com relacionamento)

Filtra apenas registros que **não possuem** os relacionamentos especificados (equivale ao `doesntHave()` do Eloquent).

**Sintaxe:**

```json
{
    "doesnt_have_relations": ["relacionamento1", "relacionamento2"]
}
```

**Exemplo:**

```json
{
    "doesnt_have_relations": ["orders", "reviews"]
}
```

**Query string:**

```
?doesnt_have_relations=["orders","reviews"]
```

Isso gera:

```php
$query->doesntHave('orders')->doesntHave('reviews');
```

Útil para encontrar registros "órfãos":

```json
{
    "doesnt_have_relations": ["category"]
}
```

→ Retorna produtos que não estão associados a nenhuma categoria.

---

## Trabalhando com relacionamentos

O pacote suporta **busca**, **ordenação** e **carregamento** através de relacionamentos Eloquent usando notação de ponto (dot notation).

### Tipos de relacionamentos suportados

- `BelongsTo` (Pertence a)
- `HasOne` (Tem um)
- `HasMany` (Tem muitos)
- `BelongsToMany` (Muitos para muitos)

### Busca em campos de relacionamentos

Você pode buscar por colunas de tabelas relacionadas usando a notação de ponto no parâmetro `search`.

#### Exemplo: Buscar produtos por nome da categoria

**Model:**

```php
class Product extends Model
{
    use ApiQueryBuilder;
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

**JSON:**

```json
{
    "search": {
        "category.name": "LIKE:Electronics",
        "price": "GT:100"
    },
    "relations": ["category"]
}
```

**SQL gerado (via subconsulta `EXISTS` / `whereHas`):**

```sql
SELECT products.*
FROM products
WHERE products.price > 100
  AND EXISTS (
    SELECT * FROM categories
    WHERE products.category_id = categories.id
      AND categories.name LIKE '%Electronics%'
  )
```

> **Nota:** A busca com notação de ponto no parâmetro `search` usa `whereHas`/`EXISTS`, não `LEFT JOIN`. O `LEFT JOIN` é usado pelo parâmetro `order_by` quando há notação de ponto. Veja [Falsos positivos na notação com ponto](#falsos-positivos-na-notação-com-ponto) para entender quando essa diferença importa.

#### Busca em múltiplos níveis de relacionamento

Você pode buscar em relacionamentos aninhados:

```json
{
    "search": {
        "category.parent.name": "EQ:Technology",
        "store.city.name": "LIKE:São Paulo"
    },
    "relations": ["category.parent", "store.city"]
}
```

### Sub-search nested (busca aninhada)

Além da notação com ponto (`relation.column`), o pacote suporta **sub-search nested**: filtros aninhados em que a chave do `search` é o nome de um relacionamento e o valor é um objeto com os parâmetros da query a aplicar **dentro** desse relacionamento.

Internamente, isso gera `whereHas` (subconsulta `EXISTS`) no Eloquent — o mesmo mecanismo usado pela notação com ponto, mas com todas as condições agrupadas em um único subquery sobre o relacionamento.

#### Sintaxe

```json
{
    "search": {
        "relationName": {
            "search": {
                "column": "OPERADOR:valor"
            }
        }
    }
}
```

O objeto aninhado aceita os mesmos parâmetros de uma query normal (`search`, `order_by`, operadores lógicos `&&`/`||`, etc.), aplicados ao model do relacionamento.

> **Obrigatório:** As condições de filtro devem ficar dentro da chave `search`. Se você passar colunas diretamente no objeto do relacionamento (sem `search`), o pacote ainda executa o `whereHas`, mas **não aplica nenhum filtro** dentro dele — o que equivale a “tem pelo menos um registro relacionado”.

#### Exemplo: filtrar registros principais por um relacionamento

**Models (exemplo genérico):**

```php
class Record extends Model
{
    use ApiQueryBuilder;

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }
}
```

**JSON — sub-search nested:**

```json
{
    "search": {
        "entries": {
            "search": {
                "status": "EQ:active",
                "score": "GE:80"
            }
        }
    }
}
```

**SQL gerado (simplificado):**

```sql
SELECT records.*
FROM records
WHERE EXISTS (
    SELECT * FROM entries
    WHERE entries.record_id = records.id
      AND entries.status IN ('active')
      AND entries.score >= 80
)
```

Todas as condições dentro de `entries.search` precisam ser satisfeitas pelo **mesmo** registro em `entries`.

#### Exemplo: relacionamento aninhado em múltiplos níveis

```json
{
    "search": {
        "container": {
            "search": {
                "owner.name": "LIKE:Smith"
            }
        }
    }
}
```

#### Exemplo: relacionamento many-to-many

```json
{
    "search": {
        "tags": {
            "search": {
                "slug": "EQ:featured"
            }
        }
    }
}
```

### Falsos positivos na notação com ponto

Ao aplicar **múltiplas condições sobre o mesmo relacionamento** usando notação com ponto — ou seja, várias chaves `relation.column` distintas no mesmo `search` —, cada chave gera uma subconsulta `EXISTS` (`whereHas`) **independente**.

Em relacionamentos com **mais de um registro relacionado** (`HasMany`, `BelongsToMany`), condições distintas podem ser satisfeitas por **registros diferentes** do relacionamento. O registro principal aparece nos resultados mesmo que **nenhum** registro relacionado individual satisfaça todas as condições ao mesmo tempo.

#### Exemplo do problema

Considere um `Record` com duas `entries`:

| entry | status | score |
|-------|--------|-------|
| A     | active | 50    |
| B     | draft  | 90    |

**Busca com notação com ponto (incorreta para este caso):**

```json
{
    "search": {
        "entries.status": "EQ:active",
        "entries.score": "GE:80"
    }
}
```

**Por que retorna o registro (falso positivo):**

- `entries.status = active` → satisfeito pela entry A
- `entries.score >= 80` → satisfeito pela entry B
- Nenhuma entry satisfaz **ambas** as condições, mas o `Record` é retornado

**Busca correta com sub-search nested:**

```json
{
    "search": {
        "entries": {
            "search": {
                "status": "EQ:active",
                "score": "GE:80"
            }
        }
    }
}
```

Aqui as condições são avaliadas dentro de um único `whereHas` — apenas registros com pelo menos uma entry que satisfaça **todas** as condições são retornados.

#### Campos do model principal

Este problema **não se aplica** a múltiplas condições em colunas do model principal (sem notação de ponto). Condições como `"status": "EQ:active"` e `"score": "GE:80"` no mesmo `search` são aplicadas diretamente na mesma linha da tabela principal via `WHERE` — não há risco de falsos positivos por registros distintos.

Da mesma forma, combinar operadores na **mesma coluna** (ex.: `"name": "EQ:foo%&&EQ:%bar"`) também avalia todas as condições sobre o mesmo valor/campo.

> **Nota sobre `order_by`:** A ordenação por relacionamento com notação de ponto usa `LEFT JOIN` e pode causar duplicação de linhas em `HasMany`/`BelongsToMany` (veja [Limitações e boas práticas](#limitações-e-boas-práticas)). Esse é um problema distinto, relacionado à listagem/ordenação, não à semântica de filtro descrita acima.

### Guia de decisão: notação com ponto vs. sub-search nested

| Cenário | Abordagem recomendada |
|---------|----------------------|
| Uma única condição em um relacionamento (`relation.column`) | Notação com ponto |
| Relacionamento `BelongsTo` / `HasOne` com múltiplas condições no mesmo relacionamento | Notação com ponto (há no máximo um registro relacionado) |
| Relacionamento `HasMany` / `BelongsToMany` com **múltiplas condições** que devem valer para o **mesmo** registro relacionado | **Sub-search nested** (obrigatório) |
| Filtros complexos dentro do relacionamento (agrupamentos `&&`/`||`, ordenação, etc.) | **Sub-search nested** |
| Relacionamentos aninhados em vários níveis com várias condições no mesmo ramo | **Sub-search nested** (mais legível e semanticamente correto) |
| Apenas carregar/filtrar dados do relacionamento no eager loading, sem afetar quais registros principais retornam | Objeto em `relations` (veja [Configurações avançadas de relacionamentos](#configurações-avançadas-de-relacionamentos)) |

**Regra prática:** Se você precisa que duas ou mais condições sobre o **mesmo** relacionamento `HasMany`/`BelongsToMany` sejam verdadeiras para o **mesmo** registro filho, use sub-search nested. Use notação com ponto quando cada condição pode ser satisfeita independentemente ou quando há apenas um registro relacionado.

### Ordenação por relacionamento

Você pode ordenar os resultados pela coluna de uma tabela relacionada usando notação de ponto.

#### Exemplo de Model com relacionamento

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use PowerVending\LaravelApiQueryBuilder\Traits\ApiQueryBuilder;

class Product extends Model
{
    use ApiQueryBuilder;
    
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
```

#### Ordenando por coluna do relacionamento

**JSON:**

```json
{
    "order_by": {
        "brand.name": "desc",
        "created_at": "asc"
    }
}
```

**O que acontece internamente:**

1. O pacote detecta que `brand.name` é um relacionamento
2. Verifica se o método `brand()` existe no model
3. Faz um `LEFT JOIN` com a tabela `brands`
4. Ordena pelo campo `name` da tabela relacionada

**SQL gerado:**

```sql
SELECT products.*
FROM products
LEFT JOIN brands ON products.brand_id = brands.id
ORDER BY brands.name DESC, products.created_at ASC
```

#### Relacionamentos suportados

| Relacionamento | Suportado | Observação |
| --- | --- | --- |
| `BelongsTo` | Sim | `LEFT JOIN` direto |
| `HasOne` / `HasMany` | Sim | `HasMany` adiciona `GROUP BY` na PK do model |
| `MorphOne` / `MorphMany` | Sim | O `JOIN` inclui a condição de `*_type` |
| `BelongsToMany` / `MorphToMany` | Sim | `JOIN` na pivot e depois na tabela relacionada, com `GROUP BY` na PK do model. `MorphToMany` inclui a condição de `*_type` na pivot |
| `MorphTo` | Não | Lança `ApiQueryBuilderException`: o relacionamento pode apontar para várias tabelas |

### Qualificação automática de colunas

Quando você usa ordenação por relacionamento, pode ocorrer ambiguidade se ambas as tabelas tiverem colunas com o mesmo nome (ex: `id`, `name`, `created_at`).

O pacote resolve isso automaticamente qualificando as colunas do `returns` com o nome da tabela.

**Exemplo:**

```json
{
    "returns": ["id", "name", "created_at"],
    "order_by": {
        "brand.name": "desc"
    }
}
```

**SQL gerado:**

```sql
SELECT 
    products.id,
    products.name,
    products.created_at
FROM products
LEFT JOIN brands ON products.brand_id = brands.id
ORDER BY brands.name DESC
```

Sem a qualificação automática, o SQL seria:

```sql
SELECT id, name  -- ERRO: Column 'name' in SELECT is ambiguous
```

### Exemplo completo com relacionamento

**JSON completo:**

```json
{
    "_per_page": 10,
    "page": 1,
    "search": {
        "status": "EQ:active"
    },
    "relations": [
        "brand"
    ],
    "returns": [
        "id",
        "brand_id",
        "name",
        "created_at"
    ],
    "order_by": {
        "brand.name": "desc",
        "created_at": "asc"
    }
}
```

**Explicação:**
- Busca produtos com status "active"
- Carrega o relacionamento `brand` (eager loading)
- Retorna apenas as colunas especificadas
- Ordena primeiro pelo nome da marca (decrescente), depois pela data de criação (crescente)
- Pagina com 10 itens por página

### Convenção de nomes

O nome do relacionamento no JSON deve corresponder ao nome do método no Model:

```php
// No Model
public function brand()  // método em camelCase
{
    return $this->belongsTo(Brand::class);
}
```

```json
// No JSON
{
    "order_by": {
        "brand.name": "desc"  // usa o nome do método
    }
}
```

### Combinando busca e ordenação em relacionamentos

Você pode combinar busca, ordenação e eager loading em relacionamentos para queries complexas.

#### Exemplo completo: Produtos com busca e ordenação por categoria

**Models:**

```php
class Product extends Model
{
    use ApiQueryBuilder;
    
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
```

**JSON:**

```json
{
    "search": {
        "category.name": "LIKE:Electronics",
        "brand.country": "EQ:Brazil",
        "price": "BT:500;5000",
        "stock": "GT:0"
    },
    "order_by": {
        "category.name": "asc",
        "brand.name": "asc",
        "price": "desc"
    },
    "relations": ["category", "brand"],
    "returns": [
        "id",
        "name",
        "price",
        "stock",
        "category_id",
        "brand_id"
    ],
    "_per_page": 15
}
```

**SQL gerado:**

```sql
SELECT 
    products.id,
    products.name,
    products.price,
    products.stock,
    products.category_id,
    products.brand_id
FROM products
LEFT JOIN categories ON products.category_id = categories.id
LEFT JOIN brands ON products.brand_id = brands.id
WHERE categories.name LIKE '%Electronics%'
  AND brands.country IN ('Brazil')
  AND products.price BETWEEN 500 AND 5000
  AND products.stock > 0
ORDER BY 
    categories.name ASC,
    brands.name ASC,
    products.price DESC
LIMIT 15
```

### Configurações avançadas de relacionamentos

Além de buscar e ordenar usando JOINs (notação de ponto), você pode **configurar como os relacionamentos são carregados** passando objetos no parâmetro `relations`.

#### Sintaxe: Relacionamentos com configurações inline

```json
{
    "relations": [
        "simpleRelation",
        {
            "relationName": {
                "search": {...},
                "order_by": {...},
                "returns": [...],
                "limit": 10
            }
        }
    ]
}
```

**O que isso faz:**
- Aplica filtros, ordenação e limites **dentro do relacionamento**
- Útil para relacionamentos `HasMany` onde você quer os N mais recentes/relevantes
- Não usa JOIN - usa subquery no eager loading

#### Exemplo 1: Últimos 5 pedidos de cada cliente

**Model:**

```php
class Customer extends Model
{
    use ApiQueryBuilder;
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
```

**JSON:**

```json
{
    "search": {
        "status": "EQ:active"
    },
    "relations": [
        {
            "orders": {
                "order_by": {
                    "created_at": "desc"
                },
                "limit": 5
            }
        }
    ]
}
```

**Resultado:**
- Cada cliente ativo terá seus 5 pedidos mais recentes carregados

#### Exemplo 2: Produtos com reviews aprovadas e bem avaliadas

**Model:**

```php
class Product extends Model
{
    use ApiQueryBuilder;
    
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
```

**JSON:**

```json
{
    "relations": [
        "category",
        {
            "reviews": {
                "search": {
                    "status": "EQ:approved",
                    "rating": "GE:4"
                },
                "order_by": {
                    "helpful_votes": "desc",
                    "created_at": "desc"
                },
                "returns": ["id", "rating", "comment", "helpful_votes", "created_at"]
            }
        }
    ],
    "_per_page": 20
}
```

**Resultado:**
- Produtos com categoria carregada normalmente
- Apenas reviews aprovadas com rating >= 4
- Ordenadas por votos úteis e data
- Retorna apenas os campos especificados

#### Exemplo 3: Misturando configurações simples e avançadas

```json
{
    "search": {
        "status": "EQ:published"
    },
    "relations": [
        "author",
        "category",
        {
            "comments": {
                "search": {
                    "status": "EQ:approved"
                },
                "order_by": {
                    "created_at": "desc"
                }
            }
        },
        {
            "tags": {
                "order_by": {
                    "name": "asc"
                }
            }
        }
    ]
}
```

**Resultado:**
- `author` e `category`: carregados normalmente (sem filtros)
- `comments`: apenas aprovados, mais recentes primeiro
- `tags`: ordenadas alfabeticamente

#### Diferença: JOIN vs Eager Loading Configurado

**JOIN (notação de ponto em `search` ou `order_by`):**
```json
{
    "search": {
        "category.name": "LIKE:Electronics"
    }
}
```
→ Usa LEFT JOIN, afeta a query principal, filtra produtos

**Eager Loading Configurado (objeto em `relations`):**
```json
{
    "relations": [
        {
            "reviews": {
                "search": {"rating": "GE:4"}
            }
        }
    ]
}
```
→ Usa subquery separada, não afeta produtos, apenas filtra reviews carregadas

#### Casos de uso práticos

**1. Last 10 activities de cada usuário:**
```json
{
    "relations": [
        {
            "activities": {
                "order_by": {"created_at": "desc"},
                "limit": 10
            }
        }
    ]
}
```

**2. Apenas notificações não lidas:**
```json
{
    "relations": [
        {
            "notifications": {
                "search": {"read_at": "EQ:null"}
            }
        }
    ]
}
```

**3. Top 3 produtos de cada categoria:**
```json
{
    "relations": [
        {
            "products": {
                "order_by": {"sales_count": "desc"},
                "limit": 3
            }
        }
    ]
}
```

#### Limitações e boas práticas

**✅ Suportado:**
- Busca em campos de relacionamentos `BelongsTo`, `HasOne`, `HasMany`, `BelongsToMany` (via notação com ponto ou sub-search nested)
- Ordenação em campos de relacionamentos `BelongsTo`, `HasOne`
- Múltiplos relacionamentos na mesma query
- Relacionamentos aninhados (ex: `category.parent.name`)
- Sub-search nested com `whereHas`/`EXISTS` para filtros semanticamente corretos em cardinalidade muitos

**⚠️ Atenção:**
- Múltiplas chaves `relation.column` no mesmo relacionamento `HasMany`/`BelongsToMany` podem gerar [falsos positivos](#falsos-positivos-na-notação-com-ponto) — prefira [sub-search nested](#sub-search-nested-busca-aninhada)
- Relacionamentos `HasMany` em `order_by` podem gerar resultados duplicados (devido ao `LEFT JOIN`)
- Use `returns` para evitar conflitos de coluna com mesmo nome
- Sempre inclua o relacionamento no `relations` quando buscar/ordenar por ele

**💡 Dica de performance:**
- Evite muitos JOINs desnecessários
- Use índices nas colunas de foreign keys
- Considere usar `returns` para limitar as colunas selecionadas

---

## Exemplos práticos completos

### Exemplo 1: Lista de produtos com filtros básicos

**Cenário:** Listar produtos ativos, com preço entre 100 e 1000, ordenados por preço crescente.

**JSON:**

```json
{
    "search": {
        "status": "EQ:active",
        "price": "BT:100;1000"
    },
    "order_by": {
        "price": "asc"
    },
    "_per_page": 20,
    "page": 1
}
```

**Query string:**

```
GET /api/products?search={"status":"EQ:active","price":"BT:100;1000"}&order_by={"price":"asc"}&_per_page=20&page=1
```

**Controller:**

```php
public function index()
{
    return Product::query()->requestPaginate();
}
```

### Exemplo 2: Busca de usuários por nome e email

**Cenário:** Buscar usuários cujo nome ou email contenha "silva".

**JSON:**

```json
{
    "search": {
        "name": "LIKE:silva"
    },
    "returns": ["id", "name", "email", "created_at"],
    "_per_page": 50
}
```

**Nota:** Para buscar em múltiplos campos com OR, você precisará customizar a query.

### Exemplo 3: Relatório de vendas por período

**Cenário:** Vendas realizadas entre duas datas, com informações completas do cliente.

**JSON:**

```json
{
    "search": {
        "created_at": "BT:2024-01-01;2024-12-31",
        "status": "EQ:completed"
    },
    "relations": ["customer", "items", "items.product"],
    "order_by": {
        "created_at": "desc"
    },
    "returns": [
        "id",
        "customer_id",
        "total",
        "status",
        "created_at"
    ]
}
```

### Exemplo 4: Listagem com relacionamento ordenado

**Cenário:** Produtos ordenados pelo nome da marca.

**JSON:**

```json
{
    "search": {
        "status": "EQ:active"
    },
    "relations": ["brand"],
    "order_by": {
        "brand.name": "asc",
        "id": "asc"
    },
    "_per_page": 25
}
```

### Exemplo 5: Clientes com últimos pedidos filtrados e ordenados

**Cenário:** Listar clientes ativos com seus 5 últimos pedidos completados.

**Models:**

```php
class Customer extends Model
{
    use ApiQueryBuilder;
    
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
    
    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
```

**JSON:**

```json
{
    "search": {
        "status": "EQ:active"
    },
    "relations": [
        "address",
        {
            "orders": {
                "search": {
                    "status": "EQ:completed"
                },
                "order_by": {
                    "completed_at": "desc"
                },
                "returns": ["id", "total", "status", "completed_at"],
                "limit": 5
            }
        }
    ],
    "order_by": {
        "created_at": "desc"
    },
    "_per_page": 20
}
```

**Resultado:**
- Lista clientes ativos ordenados por data de cadastro (mais recentes primeiro)
- Carrega o endereço de cada cliente
- Carrega apenas os 5 pedidos mais recentes que estão completados
- Retorna apenas os campos especificados dos pedidos
- Pagina 20 clientes por vez

**Controller:**

```php
public function index()
{
    return Customer::requestPaginate();
}
```

### Exemplo 6: Blog posts com comments aprovados e tags ordenadas

**Cenário:** Posts publicados com comentários aprovados e tags ordenadas alfabeticamente.

**JSON:**

```json
{
    "search": {
        "status": "EQ:published",
        "published_at": "LE:2026-04-14"
    },
    "relations": [
        "author",
        "category",
        {
            "comments": {
                "search": {
                    "status": "EQ:approved",
                    "parent_id": "EQ:null"
                },
                "order_by": {
                    "created_at": "desc"
                }
            }
        },
        {
            "tags": {
                "order_by": {
                    "name": "asc"
                }
            }
        }
    ],
    "order_by": {
        "published_at": "desc"
    },
    "_per_page": 15
}
```

**Resultado:**
- Posts publicados até hoje
- Autor e categoria carregados normalmente
- Apenas comentários aprovados de primeiro nível (sem parent), mais recentes primeiro
- Tags ordenadas alfabeticamente
- Posts ordenados por data de publicação (mais recentes primeiro)

### Exemplo 7: Exportação com todos os campos exceto sensíveis

**Cenário:** Exportar todos os dados de usuários exceto campos sensíveis.

**JSON:**

```json
{
    "excepts": ["password", "remember_token", "api_token", "two_factor_secret"],
    "limit": 10000
}
```

### Exemplo 8: Dashboard com múltiplos filtros

**Cenário:** Dashboard de produtos com múltiplos filtros aplicados.

**JSON:**

```json
{
    "search": {
        "category_id": "EQ:5",
        "stock": "GT:0",
        "price": "LT:5000",
        "name": "LIKE:Notebook",
        "is_featured": "EQ:1"
    },
    "relations": ["category", "manufacturer", "reviews"],
    "order_by": {
        "featured_order": "asc",
        "price": "asc"
    },
    "returns": [
        "id",
        "name",
        "price",
        "stock",
        "category_id",
        "manufacturer_id"
    ],
    "_per_page": 12,
    "page": 1
}
```

---

## Customizações avançadas

### Criar um operador customizado

Se os operadores padrão não atendem sua necessidade, você pode criar operadores personalizados.

#### Passo 1: Criar a classe do operador

Crie um arquivo em `app/SearchCallbacks/CustomOperator.php`:

```php
<?php

namespace App\SearchCallbacks;

use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\AbstractCallback;

class CustomOperator extends AbstractCallback
{
    // Define o prefixo do operador
    public const OPERATOR = 'CUSTOM:';

    /**
     * Executa a lógica do operador
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param string $column
     * @param string $value
     * @return void
     */
    public function __invoke($builder, string $column, string $value)
    {
        // Sua lógica customizada aqui
        // Exemplo: busca case-sensitive
        $builder->whereRaw("BINARY {$column} = ?", [$value]);
    }
}
```

#### Passo 2: Registrar o operador na configuração

Edite `config/api-query-builder.php`:

```php
use App\SearchCallbacks\CustomOperator;

return [
    'operators' => [
        CustomOperator::class,  // Adicione no topo (lembre da ordem!)
        // ... outros operadores
    ],
];
```

#### Passo 3: Usar o operador

```json
{
    "search": {
        "name": "CUSTOM:SuaEmpresa"
    }
}
```

### Exemplos de operadores customizados úteis

#### Operador IN (valores múltiplos)

```php
class InOperator extends AbstractCallback
{
    public const OPERATOR = 'IN:';

    public function __invoke($builder, string $column, string $value)
    {
        // Espera valores separados por vírgula: "IN:1,2,3,4"
        $values = explode(',', $value);
        $builder->whereIn($column, $values);
    }
}
```

**Uso:**

```json
{"category_id": "IN:1,5,8,12"}
```

#### Operador NULL

```php
class IsNullOperator extends AbstractCallback
{
    public const OPERATOR = 'NULL:';

    public function __invoke($builder, string $column, string $value)
    {
        if ($value === 'true' || $value === '1') {
            $builder->whereNull($column);
        } else {
            $builder->whereNotNull($column);
        }
    }
}
```

**Uso:**

```json
{"deleted_at": "NULL:true"}   // Registros com deleted_at NULL
{"deleted_at": "NULL:false"}  // Registros com deleted_at NOT NULL
```

### Modificar query antes de executar

Você pode adicionar condições adicionais à query antes dela ser executada:

```php
public function index(Request $request)
{
    // Adiciona condições adicionais
    $query = Product::query()
        ->where('company_id', auth()->user()->company_id)
        ->where('is_deleted', false);
    
    // Processa os parâmetros JSON na query existente
    return $query->requestPaginate();
}
```

### Validar parâmetros antes de processar

```php
public function index(Request $request)
{
    // Valida os parâmetros
    $request->validate([
        'search' => 'sometimes|json',
        'order_by' => 'sometimes|json',
        '_per_page' => 'sometimes|integer|min:1|max:100',
    ]);
    
    return Product::requestPaginate();
}
```

### Sistema de tipos (`types`)

O sistema de **types** é o mecanismo responsável por preparar, transformar e validar os valores de busca enviados pelo frontend *antes* que a query SQL seja gerada. Isso garante que os dados cheguem ao banco no formato correto para comparação.

Por exemplo:
*   **Booleanos:** Transforma `"true"` ou `"1"` em um booleano real (`true`), evitando erros de tipo no banco.
*   **JSON:** Aplica `json_encode` em objetos/arrays para que a comparação exata no banco de dados seja possível.

#### Como o tipo de um campo é descoberto?
O tipo da coluna é resolvido automaticamente na seguinte ordem de prioridade:
1.  **Casts do Model:** O pacote verifica a propriedade `$casts` no Model (ex: `'boolean'`, ou um cast customizado como `App\Casts\DynamicConfiguration::class`).
2.  **Schema do Banco de Dados:** Se não houver cast definido, ele consulta a coluna da tabela (ex: `varchar(191)`, `integer`, `json`).
3.  **Fallback (`generic`):** Caso não encontre nenhuma informação, assume o tipo genérico `generic`.

---

#### Classes de Tipo Disponíveis

Todas as classes de tipo estendem `AbstractType` (caminho: `src/Types/AbstractType.php`) e ficam sob o namespace `PowerVending\LaravelApiQueryBuilder\Types`.

1.  **`AbstractType` (Classe Base Abstrata):**
    É a classe mãe de todos os tipos. Define os métodos:
    *   `abstract public static function name(): string`: Retorna o identificador do tipo (deve bater com o cast do Model ou tipo de coluna no banco).
    *   `public function prepare(array $values, ?SearchParserInterface $searchParser = null): array`: Recebe a lista de valores a serem pesquisados e o parser da busca. Retorna os valores prontos para a query. Por padrão, retorna os valores inalterados.

2.  **`BooleanType` (Validação Booleana):**
    Identificado pelo nome `'boolean'`. Ele limpa e valida os valores booleano recebidos (como `true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`) convertendo-os para booleanos reais usando `filter_var()`. Se o valor for inválido, lança uma `ApiQueryBuilderException` com a mensagem `Wrong argument type provided`.

3.  **`GenericType` (Fallback Padrão):**
    Identificado pelo nome `'generic'`. Ele não altera nem valida os valores (apenas os retorna inalterados). Funciona como o comportamento padrão para qualquer tipo não mapeado explicitamente.

---

#### O Registry de Configuração e o lookup no `TypesConfig`

O registro dos tipos ativos é feito no arquivo `config/api-query-builder.php` através da chave `types`:

```php
'types' => [
    PowerVending\LaravelApiQueryBuilder\Types\GenericType::class,
    PowerVending\LaravelApiQueryBuilder\Types\BooleanType::class,
],
```

A classe `TypesConfig` (`src/Config/TypesConfig.php`) gerencia esse registro e resolve qual classe deve processar o valor através do método `getTypeClassFromTypeName(string $typeName)`.

##### Fluxo de Resolução do Lookup:
1.  **Verificação Direta:** Ele busca se o tipo resolvido (`$typeName`) está mapeado no registry de `types` (usando o retorno do método `name()`). Se encontrar, retorna a instância do tipo correspondente.
2.  **Fallback para `generic`:** Se o tipo não estiver mapeado (por exemplo, um campo de texto sem cast registrado), ele verifica se o `GenericType` está registrado no registry. Se sim, retorna a instância do `GenericType`.
3.  **Exceção:** Caso o tipo não esteja mapeado e o `GenericType` também não esteja registrado no registry, ele lança uma `ApiQueryBuilderException`: `"No valid callback for '$typeName' type."`.

---

#### Integração Interna com `CategorizedValues`

Toda a transformação e distribuição de dados acontece no componente `CategorizedValues` (`src/CategorizedValues.php`).

Quando o pacote processa um filtro, o `CategorizedValues` faz o seguinte:
1.  **Identifica o Tipo:** No construtor, ele descobre o tipo apropriado usando o `TypesConfig`:
    ```php
    $this->type = (new TypesConfig())->getTypeClassFromTypeName($this->searchParser->type);
    ```
2.  **Categoriza os Valores:** Separa os valores de filtro em 4 grupos ("buckets") de acordo com os micro-operadores (como `!`, `%`, `null`, `!null`):
    *   `$and`: Valores com match exato ou parcial positivo.
    *   `$andLike`: Valores textuais (LIKE) positivos.
    *   `$not`: Valores com negação.
    *   `$notLike`: Valores textuais com negação.
3.  **Aplica a Transformação (`format()`):** Executa o método `prepare()` da classe de tipo em cada um dos buckets para garantir que todos os valores passem pela transformação ou validação devida:
    ```php
    $this->and     = $this->type->prepare($this->and, $this->searchParser);
    $this->andLike = $this->type->prepare($this->andLike, $this->searchParser);
    $this->not     = $this->type->prepare($this->not, $this->searchParser);
    $this->notLike = $this->type->prepare($this->notLike, $this->searchParser);
    ```

---

#### Criando um Tipo Customizado

Para campos complexos ou personalizados, você pode criar o seu próprio tradutor de tipo.

##### Exemplo de Caso de Uso: Campo JSON
Suponha que você tenha um cast customizado chamado `JsonColumn`. Quando o frontend filtrar por igualdade (`EQ`), você precisa aplicar `json_encode` no valor para buscar corretamente no banco. Porém, se for uma busca textual (`LIKE`), você quer o valor cru.

##### 1. Criar a classe do tipo
```php
<?php

namespace App\Types;

use PowerVending\LaravelApiQueryBuilder\Types\AbstractType;
use PowerVending\LaravelApiQueryBuilder\SearchParserInterface;

class JsonColumnType extends AbstractType
{
    public static function name(): string
    {
        // Deve ser o FQCN (caminho completo) do seu cast no Model
        return \App\Casts\JsonColumn::class;
    }

    public function prepare(array $values, ?SearchParserInterface $searchParser = null): array
    {
        $operator = $searchParser?->getOperator();

        // Operadores de texto: mantém o valor cru para buscas parciais
        if (in_array($operator, ['LIKE:', 'STARTS_WITH:', 'ENDS_WITH:'], true)) {
            return $values;
        }

        // Operador de comparação exata (EQ): serializa o valor para JSON
        return array_map(function ($value) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }, $values);
    }
}
```

##### 2. Registrar no config (`config/api-query-builder.php`)
```php
'types' => [
    \PowerVending\LaravelApiQueryBuilder\Types\GenericType::class,
    \PowerVending\LaravelApiQueryBuilder\Types\BooleanType::class,
    \App\Types\JsonColumnType::class, // Registro do seu tipo customizado
],
```

---

### Operadores por cast (`cast_operators`)

A chave `cast_operators` no arquivo `config/api-query-builder.php` permite restringir quais operadores de busca estão disponíveis para campos do Model, baseando-se no cast configurado para eles. Isso serve como uma camada extra de validação e segurança.

#### Como funciona a resolução de chaves?
Quando o pacote valida os operadores de um campo, ele resolve a chave configurada no `cast_operators` da seguinte forma:
1.  **Busca Exata:** Procura pelo nome exato do tipo ou FQCN do cast (ex: `App\Casts\DynamicConfiguration::class`).
2.  **Normalização:** Se não encontrar, ele normaliza o tipo para lowercase e remove sufixos de parênteses (ex: `varchar(191)` vira `varchar`).
3.  **Sem restrição:** Se nenhuma chave corresponder, assume que o campo não possui restrição de operadores.

#### Exemplo de Configuração Real (Projeto Engineering)
No projeto **Engineering**, os campos com casts customizados `DynamicConfiguration` e `DynamicProperty` restringem os operadores permitidos no arquivo de configuração `config/api-query-builder.php`:

```php
use App\Casts\DynamicConfiguration;
use App\Casts\DynamicProperty;
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\{
    Equals, Like, Between, LessThan, GreaterThan, StartsWith, EndsWith, NotEquals, NotBetween, LessThanOrEqual, GreaterThanOrEqual
};

return [
    // ...
    'cast_operators' => [
        // Restrição para o cast de configurações dinâmicas
        DynamicConfiguration::class => [
            Equals::class,
            Like::class,
            Between::class,
            LessThan::class,
            GreaterThan::class,
            StartsWith::class,
            EndsWith::class,
            NotEquals::class,
            NotBetween::class,
            LessThanOrEqual::class,
            GreaterThanOrEqual::class,
        ],
        // Restrição para o cast de propriedades dinâmicas
        DynamicProperty::class => [
            Equals::class,
            Like::class,
            Between::class,
            LessThan::class,
            GreaterThan::class,
            StartsWith::class,
            EndsWith::class,
            NotEquals::class,
            NotBetween::class,
            LessThanOrEqual::class,
            GreaterThanOrEqual::class,
        ],
    ],
];
```

#### Efeito no Schema e na Validação
1.  **Schema da API:** A rota `/schema` do recurso só listará os operadores configurados para estes campos.
2.  **Validação:** Se o frontend tentar rodar uma query não permitida (ex: `{"configuration": "JSON_SEARCH:$.key"}`), a API rejeitará e retornará a exceção `ApiQueryBuilderException` com a mensagem:
    > `Operator 'JSON_SEARCH:' is not allowed for cast type 'App\Casts\DynamicConfiguration' on column 'configuration'.`

---

### Como `types` e `cast_operators` trabalham juntos

Eles atuam em conjunto sobre o **mesmo tipo resolvido** do campo, mas em momentos e com responsabilidades bem definidas:

1.  **Validação de Operador (`cast_operators`):**
    O pacote intercepta a requisição e verifica se o operador enviado pelo frontend é aceito pelo cast do campo (ex: *"O operador `LIKE:` é permitido para o cast `DynamicConfiguration`?"*). Se sim, a requisição continua.
2.  **Preparação e Formatação de Dados (`types`):**
    O valor do filtro é enviado para a classe de tipo associada (ex: `DynamicConfigurationType`), que prepara a formatação do valor (ex: faz um `json_encode` para busca `EQ` ou mantém o valor cru para busca `LIKE`).
3.  **Montagem da Query:**
    Com o operador validado e os valores devidamente convertidos para o formato que o banco de dados espera, o pacote monta a query SQL.

| Aspecto | `cast_operators` | `types` |
| :--- | :--- | :--- |
| **Responsabilidade** | Restringir quais operadores são permitidos | Transformar/validar o valor do filtro (`prepare()`) |
| **Momento de Execução** | Na construção do parser da query e na rota `/schema` | Durante o mapeamento dos filtros em `CategorizedValues::format()` |
| **Lookup de Classe** | Mapeamento no `cast_operators` do config | Mapeamento de `name()` no registry de `types` do config |
| **Fallback** | Fluxo normal do tipo da coluna no banco (sem restrições) | Tipo fallback `GenericType` (sem transformações) |

---

## Erros retornados pela API

Quando uma requisição contém parâmetros inválidos, o pacote lança exceções que podem ser mapeadas para respostas HTTP 400. Esta seção descreve cada mensagem de erro, sua causa e como corrigi-la.

---

### `Relation '<RELATION>' does not exist on model '<MODEL_CLASS>'.`

**Exceção:** `InvalidRelationException`

**Causa:** Foi informado um relacionamento que não existe no model (ou em algum nível de relacionamento aninhado).

**Situações em que pode ocorrer:**

1. No parâmetro `relations`.
2. No parâmetro `search` quando a chave representa relacionamento (ex.: sub-search em objeto ou notação com ponto como `relation.column`).
3. No parâmetro `doesnt_have_relations`.

**Exemplos que causam o erro:**

```json
{
    "relations": ["unknown_relation"]
}
```

```json
{
    "search": {
        "unknown_relation": {
            "search": {
                "id": "EQ:1"
            }
        }
    }
}
```

```json
{
    "search": {
        "unknown_relation.description": "EQ:abc"
    }
}
```

```json
{
    "doesnt_have_relations": ["unknown_relation"]
}
```

**Solução:**

1. Verifique o nome da relação no model Eloquent e use o nome correto.
2. Em relações aninhadas, valide cada segmento do caminho (ex.: `orders.items.product`).
3. Se necessário, padronize o nome enviado pelo frontend para o método real da relação no backend.

---

Os erros abaixo são produzidos pela classe `InvalidOperatorUsageException` e **a mensagem é segura para ser exibida diretamente ao consumidor da API**.

---

### `The '<OP>' operator is not supported for text-type fields. Only comparable field types (numeric, date, etc.) are allowed.`

**Operadores afetados:** `GT`, `GE`, `LT`, `LE`

**Causa:** O operador de comparação foi usado em uma coluna cujo tipo resolvido pelo pacote é textual (`string`, `varchar`, `char`, `text`, `longtext`, etc.). Esses operadores exigem tipos comparáveis (numérico, data, etc.).

**Exemplo que causa o erro:**
```json
{"name": "GE:Notebook"}
```

**Soluções possíveis:**

1. Use `EQ:` ou `LIKE:` para colunas de texto.
2. Se a coluna armazena datas ou números como string (ex: `VARCHAR` contendo `"2024-01-15"`), aplique um cast personalizado no model para que o pacote não trate o campo como texto puro:

```php
// App/Models/Product.php
protected $casts = [
    'expires_at' => \App\Casts\Iso8601DateTimeString::class,
];
```

Isso faz com que o tipo resolvido pelo pacote deixe de ser `string` e os operadores `GT`/`GE`/`LT`/`LE` passem a funcionar.

---

### `The '<OP>' operator expects exactly one value, but multiple were provided.`

**Operadores afetados:** `GT`, `GE`, `LT`, `LE`

**Causa:** Foi passado mais de um valor separado por `;` para um operador que aceita apenas um valor.

**Exemplo que causa o erro:**
```json
{"price": "GE:100;200"}
```

**Solução:** Passe apenas um valor:
```json
{"price": "GE:100"}
```

Se o objetivo é um intervalo, use `BT:` (between):
```json
{"price": "BT:100;200"}
```

---

### `No value was provided for the '<OP>' operator.`

**Operadores afetados:** `GT`, `GE`, `LT`, `LE`

**Causa:** O operador foi enviado sem nenhum valor após os dois pontos.

**Exemplo que causa o erro:**
```json
{"price": "GE:"}
```

**Solução:** Sempre forneça um valor após o operador:
```json
{"price": "GE:100"}
```

---

### `The '<OP>' operator expects exactly 2 values, but N were provided.`

**Operadores afetados:** `BT`, `NB`

**Causa:** O operador `BT:` (between) ou `NB:` (not between) exige exatamente dois valores separados por `;`, mas a quantidade recebida foi diferente.

**Exemplos que causam o erro:**
```json
{"price": "BT:100"}          // apenas 1 valor
{"price": "BT:100;200;300"}  // 3 valores
```

**Solução:** Forneça exatamente dois valores:
```json
{"price": "BT:100;500"}
```

---

### `Operator '<OP>' is not allowed for cast type '<TYPE>' on column '<COLUMN>'.`

**Exceção:** `ApiQueryBuilderException`

**Causa:** O campo pesquisado possui um cast definido no model (`$casts`) e o operador utilizado não está na lista de operadores permitidos para esse cast em `cast_operators` no arquivo `config/api-query-builder.php`.

Este erro só ocorre quando a chave `cast_operators` está configurada com ao menos um mapeamento de cast.

**Exemplo que causa o erro:**

Model com cast:
```php
protected $casts = [
    'is_active' => 'boolean',
];
```

Config:
```php
'cast_operators' => [
    'boolean' => [\PowerVending\LaravelApiQueryBuilder\SearchCallbacks\Equals::class],
],
```

Requisição inválida:
```json
{"is_active": "GT:1"}
```

**Solução:**

1. Use um dos operadores permitidos para o cast do campo (consulte a rota de schema para ver quais estão disponíveis).
2. Se o operador for necessário, adicione-o à lista de `cast_operators` para o cast correspondente no arquivo de configuração.
3. Se não quiser restrição para esse cast, remova a entrada de `cast_operators`.

### `Wrong argument type provided`

**Exceção:** `ApiQueryBuilderException`

**Causa:** Um filtro foi aplicado em uma coluna com cast `boolean` (booleano), mas o valor enviado não pôde ser convertido para booleano.

**Exemplo que causa o erro:**
```json
{"is_active": "EQ:talvez"}
```

**Solução:** Envie apenas valores booleanos aceitos (`true`, `false`, `1`, `0`, `yes`, `no`, `on`, `off`).

---

### `No valid callback for '<TYPE>' type.`

**Exceção:** `ApiQueryBuilderException`

**Causa:** O tipo identificado para a coluna no banco de dados ou no Model não possui uma classe de tratamento correspondente registrada em `types` (e o fallback geral `GenericType` não está configurado).

**Solução:**
1. Verifique se `GenericType::class` está registrado na chave `types` do arquivo de configuração `config/api-query-builder.php`. Ele funciona como o fallback padrão.
2. Se o campo exige uma conversão customizada, crie e registre seu tipo customizado na configuração (veja [Criando e Registrando um Tipo Customizado](#criando-e-registrando-um-tipo-customizado)).

---

## Solução de problemas

### Erro: "Column not found"

**Sintoma:** Erro SQL dizendo que uma coluna não existe.

**Causas possíveis:**

1. Nome da coluna digitado errado no JSON
2. Coluna realmente não existe na tabela
3. Tentando acessar coluna de relacionamento sem JOIN

**Solução:**

Verifique se a coluna existe:

```bash
php artisan tinker
>>> Schema::hasColumn('products', 'nome_da_coluna')
```

Se for coluna de relacionamento, use notação de ponto:

```json
{"order_by": {"category.name": "asc"}}
```

### Erro: "Ambiguous column"

**Sintoma:** `Column 'id' in SELECT is ambiguous`

**Causa:** Você está usando `order_by` com relacionamento mas não especificou `returns`, ou especificou colunas sem qualificação.

**Solução:**

Sempre especifique `returns` quando usar ordenação por relacionamento:

```json
{
    "returns": ["id", "name"],
    "order_by": {"category.name": "asc"}
}
```

O pacote irá qualificar automaticamente as colunas.

### Erro: "Forbidden column"

**Sintoma:** Tentativa de acessar uma coluna proibida.

**Causa:** A coluna está na lista de colunas proibidas (via model ou config).

**Solução:**

1. Verifique a propriedade `$apiQueryBuilderForbiddenColumns` na model
2. Verifique `config/api-query-builder.php` → `global_forbidden_columns`
3. Verifique `config/api-query-builder.php` → `model_options[YourModel::class]['forbidden_columns']`
4. Remova a coluna da lista ou use outra coluna para buscar

Isso é uma proteção de segurança. Se você realmente precisa acessar essa coluna:

1. Remova da lista de colunas proibidas (não recomendado para campos sensíveis)
2. Crie um endpoint separado específico para esse caso

### Operador não funciona

**Sintoma:** Operador sendo interpretado como valor literal.

**Causas possíveis:**

1. Operador não está registrado na configuração
2. Ordem dos operadores está incorreta
3. Sintaxe incorreta

**Solução:**

Verifique a ordem no `config/api-query-builder.php`:

```php
'operators' => [
    // MAIORES PRIMEIRO
    \PowerVending\LaravelApiQueryBuilder\SearchCallbacks\StartsWith::class,
    \PowerVending\LaravelApiQueryBuilder\SearchCallbacks\EndsWith::class,
    // ... depois os menores
],
```

Limpe o cache de configuração:

```bash
php artisan config:clear
php artisan config:cache
```

### Performance lenta

**Sintoma:** Queries demorando muito tempo.

**Causas possíveis:**

1. Falta de índices no banco de dados
2. Muitos relacionamentos sendo carregados
3. Paginação com número muito alto de registros

**Soluções:**

Adicione índices nas colunas mais filtradas:

```php
// Migration
Schema::table('products', function (Blueprint $table) {
    $table->index('status');
    $table->index('category_id');
    $table->index('price');
    $table->index(['status', 'category_id']); // índice composto
});
```

Use `limit` ou `_per_page` para limitar resultados:

```json
{"_per_page": 25}
```

Evite carregar relacionamentos desnecessários:

```json
{
    "relations": ["category"],  // apenas o necessário
    // evite: "relations": ["category", "reviews", "images", "manufacturer"]
}
```

### JSON inválido na query string

**Sintoma:** Erro de parsing JSON.

**Causa:** JSON mal formatado na URL.

**Solução:**

Sempre encode o JSON na URL:

```javascript
// JavaScript
const params = {
    search: JSON.stringify({
        name: "LIKE:Produto",
        status: "EQ:active"
    })
};

const url = '/api/products?' + new URLSearchParams(params);
```

Ou use ferramentas que fazem isso automaticamente (Axios, etc).

### Relacionamento não encontrado

**Sintoma:** Erro dizendo que o relacionamento não existe.

**Causa:** Nome do relacionamento incorreto ou método não existe no model.

**Solução:**

Verifique se o método existe no model:

```php
class Product extends Model
{
    // O método deve existir e ser público
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
```

Use o nome exato do método no JSON:

```json
{
    "order_by": {"category.name": "asc"}  // 'category' = nome do método
}
```

---

## Testes

O pacote inclui testes automatizados usando PHPUnit.

### Executar todos os testes

```bash
composer test
```

Ou com PHPUnit diretamente:

```bash
vendor/bin/phpunit
```

### Executar testes específicos

```bash
vendor/bin/phpunit --filter NomeDoTeste
```

### Estrutura de testes

Os testes estão localizados em `packages/api-query-builder/tests/`:

```
tests/
├── Feature/              # Testes de integração
│   ├── SearchTest.php
│   ├── OrderByTest.php
│   └── RelationTest.php
├── Unit/                 # Testes unitários
│   ├── OperatorTest.php
│   └── ConfigTest.php
└── TestCase.php         # Classe base de testes
```

### Criar novos testes

Para adicionar novos testes, crie uma classe que estenda `TestCase`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;

class CustomFeatureTest extends TestCase
{
    public function test_custom_operator_works()
    {
        // Arrange
        Product::factory()->create(['name' => 'Test Product']);
        
        // Act
        $result = Product::jsonSearch(['name' => 'CUSTOM:Test']);
        
        // Assert
        $this->assertCount(1, $result);
    }
}