export const tools = [
    {
        type: "function",
        function: {
            name: "add_transaction",
            description: "Adicionar uma nova transação (receita ou despesa)",
            parameters: {
                type: "object",
                properties: {
                    type: { type: "string", enum: ["income", "expense"], description: "Tipo da transação" },
                    amount: { type: "number", description: "Valor da transação" },
                    description: { type: "string", description: "Descrição ou nome da transação" },
                    category: {
                        type: "string",
                        enum: [
                            "Alimentação", "Mercado", "Transporte", "Lazer", "Roupas", "Jogos",
                            "Saúde", "Esportes", "Investimento", "Educação", "Moradia", "Contas",
                            "Assinaturas", "Beleza", "Pets", "Viagem", "Presentes", "Salário",
                            "Extra", "Outros"
                        ],
                        description: "Categoria da transação. Se não especificado, inferir a melhor opção ou usar Outros."
                    },
                    date: { type: "string", description: "Data no formato YYYY-MM-DD. Se hoje, usar a data atual." }
                },
                required: ["type", "amount", "description"]
            }
        }
    },
    {
        type: "function",
        function: {
            name: "add_recurring_transaction",
            description: "Adicionar uma conta fixa ou recorrência mensal",
            parameters: {
                type: "object",
                properties: {
                    type: { type: "string", enum: ["income", "expense"] },
                    amount: { type: "number" },
                    description: { type: "string" },
                    category: { type: "string" },
                    day_of_month: { type: "number", description: "Dia do mês que ocorre (1-31)" }
                },
                required: ["type", "amount", "description", "day_of_month"]
            }
        }
    },
    {
        type: "function",
        function: {
            name: "get_financial_summary",
            description: "Consultar saldo atual ou resumo financeiro",
            parameters: {
                type: "object",
                properties: {},
            }
        }
    },
    {
        type: "function",
        function: {
            name: "delete_transaction_by_description",
            description: "Remover uma transação recente com base na descrição ou valor",
            parameters: {
                type: "object",
                properties: {
                    description: { type: "string" }
                },
                required: ["description"]
            }
        }
    },
    {
        type: "function",
        function: {
            name: "add_investment",
            description: "Adicionar um novo investimento (ex: ações, renda fixa, criptomoedas). Use quando o usuário mencionar que INVESTIU ou colocou dinheiro em um ATIVO.",
            parameters: {
                type: "object",
                properties: {
                    name: { type: "string", description: "Nome do investimento (ex: Bitcoin, Tesouro IPCA, PETR4)" },
                    type: {
                        type: "string",
                        enum: ["Ações", "Renda Fixa", "FII", "Criptomoeda", "ETF", "Tesouro Direto", "CDB", "LCI/LCA", "Outro"],
                        description: "Tipo do investimento"
                    },
                    invested_value: { type: "number", description: "Valor investido original" },
                    current_value: { type: "number", description: "Valor ATUAL. Se o usuário informar taxa (ex: 100% CDI), TENTE CALCULAR o valor estimado hoje. Se não souber calcular, use o valor investido." },
                    start_date: { type: "string", description: "Data no formato YYYY-MM-DD" },
                    benchmark: { type: "string", enum: ["CDI", "IPCA", "SELIC", "FIXADO", "IBOV"], description: "Indexador (se houver)" },
                    yield_rate: { type: "number", description: "Taxa do indexador (ex: 100 para 100% CDI, 12 para 12% a.a.)" }
                },
                required: ["name", "type", "invested_value"]
            }
        }
    },
    {
        type: "function",
        function: {
            name: "add_contribution",
            description: "Adicionar um aporte a um investimento JÁ EXISTENTE. Use quando o usuário disser que APORTOU mais dinheiro em algo.",
            parameters: {
                type: "object",
                properties: {
                    investment_name: { type: "string", description: "Nome do investimento onde aportar" },
                    amount: { type: "number", description: "Valor do aporte" },
                    date: { type: "string", description: "Data no formato YYYY-MM-DD" },
                    notes: { type: "string", description: "Observações opcionais" }
                },
                required: ["investment_name", "amount"]
            }
        }
    },
    {
        type: "function",
        function: {
            name: "update_investment_value",
            description: "Atualizar o VALOR ATUAL de um investimento (rendimento/cotação), SEM ser um aporte novo. Use quando o usuário disser: 'Meu Bitcoin agora vale X' ou 'O saldo do Tesouro hoje é Y'.",
            parameters: {
                type: "object",
                properties: {
                    investment_name: { type: "string", description: "Nome do investimento" },
                    current_value: { type: "number", description: "Novo valor total atual" },
                    date: { type: "string", description: "Data da atualização" }
                },
                required: ["investment_name", "current_value"]
            }
        }
    }
];
