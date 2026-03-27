import React, { useState, useEffect } from "react";
import { Api, token } from "../services/api";

interface CompleteFormData {

    tokenCard: string;
    rrn: string;
    intRef: string;
    orderId: string;
    amount: string;
    currency: string;

}

export const Complete: React.FC = () => {

    const [form, setForm] = useState<CompleteFormData>({

        tokenCard: "",
        rrn: "",
        intRef: "",
        orderId: "",
        amount: "",
        currency: "CRC"

    });

    const [response, setResponse] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [loading, setLoading] = useState(false);


    useEffect(() => {

        setForm({

            tokenCard: "",
            rrn: "",
            intRef: "",
            orderId: "",
            amount: "",
            currency: "CRC"

        });

        setResponse("");
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



    const handleComplete = async () => {

        if (
            !form.tokenCard ||
            !form.rrn ||
            !form.intRef ||
            !form.orderId ||
            !form.amount
        ) {

            setErrorMessage("Debe completar todos los campos requeridos.");
            return;

        }

        setLoading(true);

        try {

            const result = await Api.complete({

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
                                Completitud
                            </h5>


                            <div className="mb-3">

                                <label className="form-label">

                                    TokenCard *

                                </label>

                                <input
                                    name="tokenCard"
                                    className="form-control"
                                    value={form.tokenCard}
                                    onChange={handleChange}
                                    placeholder="4BAE65********0FDDEA8"
                                />

                            </div>






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
                                    RRN *
                                </label>

                                <input
                                    name="rrn"
                                    className="form-control"
                                    value={form.rrn}
                                    onChange={handleChange}
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
                                />

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
                                />

                            </div>



                            <button
                                className="btn btn-primary w-100"
                                onClick={handleComplete}
                                disabled={loading}
                            >

                                {loading
                                    ? "Procesando..."
                                    : "Completar Transacción"
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