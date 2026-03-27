import React, { useState, useEffect } from "react";
import { Api, token } from "../services/api";
import { v4 as uuidv4 } from "uuid";

interface AuthFormData {
    clientId: string;
    solicita: string;
    tokenCard: string;
    amount: string;
    currency: string;
    description: string;
    page_result: string;
}

export const PreAuth: React.FC = () => {
    const [form, setForm] = useState<AuthFormData>({
        clientId: uuidv4(),
        solicita: "dll",
        tokenCard: "",
        amount: "",
        currency: "CRC",
        description: "",
        page_result: "http://localhost:3000/3ds"
    });

    const [response, setResponse] = useState<any>(null);
    const [errorMessage, setErrorMessage] = useState("");
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setResponse(null);
        setErrorMessage("");
    }, []);

    const handleChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
    ) => {
        const { name, value } = e.target;
        setForm(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleAuth = async () => {
        if (
            !form.clientId ||
            !form.tokenCard ||
            !form.amount ||
            !form.currency ||
            !form.description ||
            !form.page_result
        ) {
            setErrorMessage("Debe completar todos los campos.");
            return;
        }

        setLoading(true);
        setErrorMessage("");

        try {
            const result = await Api.authorization({
                clientId: form.clientId,
                solicita: form.solicita,
                card: {
                    tokenCard: form.tokenCard,
                    amount: form.amount,
                    currency: form.currency,
                    description: form.description,
                    page_result: form.page_result
                },
                token
            });

            setResponse(result.data);
        } catch (e) {
            setErrorMessage("Error invocando servicio");
        }

        setLoading(false);
    };

    // Función que crea y envía el form 3DS
    const handle3DS = () => {


        const { action, creq, threeDSSessionData } = response.payResponse;

        const formElement = document.createElement("form");
        formElement.method = "POST";
        formElement.action = action;

        const addInput = (name: string, value: string) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value;
            formElement.appendChild(input);
        };

        addInput("creq", creq);
        addInput("threeDSSessionData", threeDSSessionData);

        document.body.appendChild(formElement);
        formElement.submit();
    };

    return (
        <div className="container mt-5">
            <div className="row justify-content-center">
                <div className="col-md-6 col-lg-5">
                    <div className="card shadow">
                        <div className="card-body">
                            <h5 className="mb-4 text-center">Pre-Autorización</h5>

                            <div className="mb-3">
                                <label className="form-label">ClientId *</label>
                                <input
                                    name="clientId"
                                    className="form-control"
                                    value={form.clientId}
                                    onChange={handleChange}
                                    placeholder="UUID"
                                />
                            </div>

                            <div className="mb-3">
                                <label className="form-label">Token Card *</label>
                                <input
                                    name="tokenCard"
                                    className="form-control"
                                    value={form.tokenCard}
                                    onChange={handleChange}
                                    placeholder="Token obtenido en Validar Tarjeta"
                                />
                            </div>

                            <div className="mb-3">
                                <label className="form-label">Monto *</label>
                                <input
                                    name="amount"
                                    className="form-control"
                                    value={form.amount}
                                    onChange={handleChange}
                                    placeholder="1000.00"
                                />
                            </div>

                            <div className="mb-3">
                                <label className="form-label">Moneda *</label>
                                <select
                                    name="currency"
                                    className="form-control"
                                    value={form.currency}
                                    onChange={handleChange}
                                >
                                    <option value="CRC">CRC</option>
                                    <option value="USD">USD</option>
                                </select>
                            </div>

                            <div className="mb-3">
                                <label className="form-label">Descripción *</label>
                                <input
                                    name="description"
                                    className="form-control"
                                    value={form.description}
                                    onChange={handleChange}
                                    placeholder="Compra Demo"
                                />
                                <small>En pruebas si agrega "3ds" simula el flujo 3ds</small>
                            </div>

                            <div className="mb-3">
                                <label className="form-label">URL Resultado 3DS *</label>
                                <input
                                    name="page_result"
                                    className="form-control"
                                    value={form.page_result}
                                    onChange={handleChange}
                                    placeholder="https://midominio.com/result"
                                />
                            </div>

                            <div className="d-grid">
                                <button
                                    className="btn btn-primary"
                                    onClick={handleAuth}
                                    disabled={loading}
                                >
                                    {loading ? "Procesando..." : "Autorización"}
                                </button>
                            </div>

                            {errorMessage && (
                                <div className="alert alert-danger mt-3">{errorMessage}</div>
                            )}
                        </div>
                    </div>

                    {response && (
                        <div className="card mt-4">
                            <div className="card-header">Respuesta</div>
                            <div className="card-body">
                                <pre>{JSON.stringify(response, null, 2)}</pre>

                                {/* Mostrar botón 3DS solo si status es CONFIRMED */}
                                {response.payResponse.status == "CONFIRMED" && (
                                    <div className="mt-3">
                                        <button
                                            className="btn btn-warning w-100"
                                            onClick={handle3DS}
                                        >
                                            Procesar 3DS
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};