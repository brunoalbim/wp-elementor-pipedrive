# PROJETO: PLUGINS PARA CONECTAR CRM, EMAIL MARKETING E WEBHOOK
Leia mais no arquivo @README.md

## DEPENDÊNCIAS E VERSIONAMENTO:
- O projeto usa **versões exatas e fixadas** em todas as dependências (sem `^` ou `~`) para evitar atualizações silenciosas e riscos de supply chain attack;
- Antes de instalar qualquer nova dependência, o agente DEVE avisar explicitamente ao usuário informando: o nome do pacote, a versão exata que será instalada, e se é `dependency` ou `devDependency`;

## REGRAS GERAIS:
 - Edite apenas o necessário para concluir a tarefa;
 - Nunca crie arquivos .html de teste dentro do projeto;
 - Documentação em arquivos .md só deve ser criada quando explicitamente solicitada pelo usuário — nem todo assunto/tarefa precisa ser documentado. Quando solicitada, o arquivo deve ficar dentro da pasta `/docs` (nunca solto na raiz ou em outras pastas, com exceção do `README.md`) e sempre no formato Markdown, a não ser que outro formato seja explicitamente pedido;
 - Responda sempre em Português Brasil;
 - Na interface use o idioma Português Brasil, mas no código sempre use nomenclaturas em Inglês;
 - Antes de iniciar a programar, consulte o arquivo README.md para entender o projeto;
 - Sempre pense e monte um plano de ação antes de começar a realizar modificações o código;
 - Sempre que estiver em modo de planejamento, faça perguntas importantes para entender melhor a demanda que precisa executar.

## DOCUMENTAÇÃO EM /docs:
 - Todo arquivo `.md` criado em `/docs` deve conter uma linha de **"Última atualização: DD/MM/AAAA"** (visível no topo do arquivo, abaixo do primeiro titulo ou primeiro paragráfo) — atualizar essa data sempre que o conteúdo for revisado ou editado, para permitir acompanhamento cronológico;
 - Antes de usar como referência um arquivo de `/docs` com mais de **2 meses** desde a última atualização, o agente deve validar se as informações ainda são precisas conferindo o estado atual do código/aplicação (arquivos citados ainda existem, funções/rotas mencionadas ainda têm a mesma assinatura/comportamento, etc.) antes de tomar decisões baseadas nele;
 - Se a checagem encontrar divergência, o agente deve avisar o usuário e atualizar o arquivo (corrigindo o conteúdo e a data) em vez de seguir usando a informação desatualizada.
