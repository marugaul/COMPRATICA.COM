import React, { useState, useEffect } from "react";
import { Api, token } from "../services/api";

interface ValidateCardFormData {
    card: string;
    expiration: string;
    cvv: string;
}

export const Validate: React.FC = () => {

    const [form, setForm] = useState<ValidateCardFormData>({
        card: "",
        expiration: "",
        cvv: ""
    });

    const [response, setResponse] = useState("");
    const [errorMessage, setErrorMessage] = useState("");
    const [loading, setLoading] = useState(false);


    useEffect(() => {

        setForm({
            card: "",
            expiration: "",
            cvv: ""
        });

        setResponse("");
        setErrorMessage("");

    }, []);


    const handleChange = (
        e: React.ChangeEvent<HTMLInputElement>
    ) => {

        const { name, value } = e.target;

        setForm(prev => ({
            ...prev,
            [name]: value
        }));

    };


    const handleValidate = async () => {

        if (!form.card || !form.expiration || !form.cvv) {

            setErrorMessage("Debe completar todos los campos.");
            return;
        }

        setLoading(true);

        try {

            const result = await Api.validateCard({

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

                <div className="col-md-6 col-lg-5">

                    <div className="card shadow">

                        <div className="card-body">

                            <h5 className="mb-4 text-center">
                                Validar Tarjeta
                            </h5>


                            <div className="mb-3">

                                <label className="form-label">
                                    Número de Tarjeta *
                                </label>

                                <input
                                    name="card"
                                    className="form-control"
                                    value={form.card}
                                    onChange={handleChange}
                                    placeholder="4111111111111111"
                                />

                            </div>


                            <div className="mb-3">

                                <label className="form-label">
                                    Expiración MMYY *
                                </label>

                                <input
                                    name="expiration"
                                    className="form-control"
                                    value={form.expiration}
                                    onChange={handleChange}
                                    placeholder="1228"
                                />

                            </div>


                            <div className="mb-3">

                                <label className="form-label">
                                    CVV *
                                </label>

                                <input
                                    name="cvv"
                                    className="form-control"
                                    value={form.cvv}
                                    onChange={handleChange}
                                    placeholder="123"
                                />

                            </div>


                            <div className="d-grid">

                                <button
                                    className="btn btn-primary"
                                    onClick={handleValidate}
                                    disabled={loading}
                                >

                                    {loading
                                        ? "Procesando..."
                                        : "Validar Tarjeta"}

                                </button>

                            </div>


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