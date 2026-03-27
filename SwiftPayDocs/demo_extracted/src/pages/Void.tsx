import React, { useState, useEffect } from "react";
import { Api, token } from "../services/api";

interface VoidFormData {

    amount: string;
    currency: string;
    orderId: string;
    rrn: string;
    intRef: string;
    authCode: string;

}

export const Void: React.FC = () => {

    const [form, setForm] = useState<VoidFormData>({

        amount: "",
        currency: "CRC",
        orderId: "",
        rrn: "",
        intRef: "",
        authCode: ""

    });

    const [response, setResponse] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [loading, setLoading] = useState(false);



    /*
    Inicialización
    */
    useEffect(() => {

        setForm({

            amount: "",
            currency: "CRC",
            orderId: "",
            rrn: "",
            intRef: "",
            authCode: ""

        });

        setResponse("");
        setErrorMessage("");

    }, []);



    /*
    Cambios formulario
    */
    const handleChange = (
        e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
    ) => {

        const { name, value } = e.target;

        setForm(prev => ({
            ...prev,
            [name]: value
        }));

    };



    /*
    Ejecutar Void
    */
    const handleVoid = async () => {

        if (
            !form.amount ||
            !form.orderId ||
            !form.rrn ||
            !form.intRef ||
            !form.authCode
        ) {

            setErrorMessage("Debe completar todos los campos requeridos.");
            return;

        }

        setLoading(true);

        try {

            const result = await Api.void({

                card: form,
                token

            });

            setResponse(
                JSON.stringify(result.data, null, 2)
            );

        } catch (e: any) {

            setErrorMessage("Error invocando servicio");

        }

        setLoading(false);

    };



    return (

        <div className="container mt-5">

            <div className="row justify-content-center">

                <div className="col-md-8 col-lg-6">

                    <div className="card shadow">

                        <div className="card-body">

                            <h5 className="mb-4 text-center">
                                Anulación
                            </h5>


                            <div className="mb-3">

                                <label className="form-label">
                                    Monto *
                                </label>

                                <input
                                    name="amount"
                                    className="form-control"
                                    value={form.amount}
                                    onChange={handleChange}
                                    placeholder="10.54"
                                />

                            </div>



                            <div className="mb-3">

                                <label className="form-label">
                                    Moneda *
                                </label>

                                <select
                                    name="currency"
                                    className="form-control"
                                    value={form.currency}
                                    onChange={handleChange}
                                >

                                    <option value="CRC">
                                        CRC
                                    </option>

                                    <option value="USD">
                                        USD
                                    </option>

                                </select>

                            </div>



                            <div className="mb-3">

                                <label className="form-label">
                                    Order Id *
                                </label>

                                <input
                                    name="orderId"
                                    className="form-control"
                                    value={form.orderId}
                                    onChange={handleChange}
                                    placeholder="20250814022632"
                                />

                            </div>



                            <div className="mb-3">

                                <label className="form-label">
                                    RRN *
                                </label>

                                <input
                                    name="rrn"
                                    className="form-control"
                                    value={form.rrn}
                                    onChange={handleChange}
                                    placeholder="522502927308"
                                />

                            </div>



                            <div className="mb-3">

                                <label className="form-label">
                                    Int Ref *
                                </label>

                                <input
                                    name="intRef"
                                    className="form-control"
                                    value={form.intRef}
                                    onChange={handleChange}
                                    placeholder="865019D3B2E6859B"
                                />

                            </div>



                            <div className="mb-3">

                                <label className="form-label">
                                    Auth Code *
                                </label>

                                <input
                                    name="authCode"
                                    className="form-control"
                                    value={form.authCode}
                                    onChange={handleChange}
                                    placeholder="296684"
                                />

                            </div>



                            <button
                                className="btn btn-danger w-100"
                                onClick={handleVoid}
                                disabled={loading}
                            >

                                {loading
                                    ? "Procesando..."
                                    : "Anular Transacción"
                                }

                            </button>


                            {errorMessage && (

                                <div className="alert alert-danger mt-3">

                                    {errorMessage}

                                </div>

                            )}

                        </div>

                    </div>



                    {response && (

                        <div className="card mt-4">

                            <div className="card-header">
                                Respuesta
                            </div>

                            <div className="card-body">

                                <pre>
                                    {response}
                                </pre>

                            </div>

                        </div>

                    )}

                </div>

            </div>

        </div>

    );

};