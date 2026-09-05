# Migrations — Central iGaming

Estas migrations reproduzem **fielmente** o schema completo do sistema (48 tabelas).
Foram geradas a partir do banco de produção e **validadas**: um banco vazio migrado
fica idêntico ao dump, coluna por coluna.

> ⚠️ **Nunca rode `php artisan migrate` cru num banco que já tem as tabelas** (ex.: criado
> a partir do `mybanco01.sql`). Ele tentaria recriar tabelas existentes e falharia.
> Use o fluxo correto abaixo.

---

## Cenário A — Banco NOVO / ambiente do zero (dev, teste, cliente novo)

Banco vazio → deixe as migrations criarem tudo:

```bash
php artisan migrate
```

Resultado: as 48 tabelas do sistema.

## Cenário B — Banco EXISTENTE (veio do dump SQL `mybanco01.sql`)

O banco já tem as tabelas; só precisamos **registrar** as migrations como aplicadas,
sem executá-las:

```bash
php artisan migrate:baseline   # marca todas as migrations atuais como já aplicadas
```

A partir daí, atualizações futuras funcionam normalmente:

```bash
php artisan migrate            # roda apenas as migrations NOVAS que você adicionar
```

O comando `migrate:baseline` é **idempotente** e **seguro rodar junto de updates**: ele marca
apenas as migrations cujas tabelas JÁ EXISTEM; migrations de tabelas NOVAS ficam pendentes
para o `php artisan migrate` executar. Fluxo num banco do dump que recebeu um update:

```bash
php artisan migrate:baseline   # marca o schema existente; deixa as novas pendentes
php artisan migrate            # cria as tabelas novas do update
```

---

## Fluxo de atualização de schema (para o autor)

1. Altere o schema criando uma **nova** migration (`php artisan make:migration add_campo_x_em_tabela`).
2. Teste localmente (`php artisan migrate` num banco de dev).
3. Envie a nova migration ao cliente junto do release.
4. O cliente roda `php artisan migrate` — só a nova roda; o baseline garante que as antigas são ignoradas.

Assim o schema deixa de ser SQL manual e passa a ser versionado e distribuível.
