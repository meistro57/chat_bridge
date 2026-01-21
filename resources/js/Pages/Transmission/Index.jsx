import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { 
    Select, 
    SelectContent, 
    SelectItem, 
    SelectTrigger, 
    SelectValue 
} from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import Pagination from '@/Components/Pagination';

export default function TransmissionIndex({ auth, transmissions }) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        destination: '',
        message: '',
        priority: 'medium',
        method: 'default',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('transmission.store'), {
            onSuccess: () => {
                reset();
                setIsCreateOpen(false);
            }
        });
    };

    const statusColors = {
        pending: 'bg-yellow-100 text-yellow-800',
        sent: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Transmissions
                    </h2>
                    <DialogTrigger asChild>
                        <Button onClick={() => setIsCreateOpen(true)}>
                            Create Transmission
                        </Button>
                    </DialogTrigger>
                </div>
            }
        >
            <Head title="Transmissions" />

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create New Transmission</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label>Destination</label>
                            <Input 
                                value={data.destination}
                                onChange={(e) => setData('destination', e.target.value)}
                                placeholder="Recipient or endpoint"
                                required
                            />
                            {errors.destination && (
                                <div className="text-red-500">{errors.destination}</div>
                            )}
                        </div>
                        
                        <div>
                            <label>Message</label>
                            <Textarea 
                                value={data.message}
                                onChange={(e) => setData('message', e.target.value)}
                                placeholder="Your transmission message"
                                required
                            />
                            {errors.message && (
                                <div className="text-red-500">{errors.message}</div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label>Priority</label>
                                <Select 
                                    value={data.priority}
                                    onValueChange={(value) => setData('priority', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Priority" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="low">Low</SelectItem>
                                        <SelectItem value="medium">Medium</SelectItem>
                                        <SelectItem value="high">High</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label>Method</label>
                                <Input 
                                    value={data.method}
                                    onChange={(e) => setData('method', e.target.value)}
                                    placeholder="Transmission method"
                                />
                            </div>
                        </div>

                        <Button 
                            type="submit" 
                            disabled={processing}
                            className="w-full"
                        >
                            {processing ? 'Sending...' : 'Create Transmission'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {transmissions.data.length === 0 ? (
                        <Card>
                            <CardContent className="pt-6 text-center">
                                No transmissions yet. Create your first one!
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-4">
                            {transmissions.data.map((transmission) => (
                                <Card key={transmission.id}>
                                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                        <CardTitle className="text-sm font-medium">
                                            To: {transmission.destination}
                                        </CardTitle>
                                        <Badge 
                                            className={`${statusColors[transmission.status]}`}
                                        >
                                            {transmission.status}
                                        </Badge>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-sm text-gray-600">
                                            {transmission.message}
                                        </div>
                                        <div className="mt-2 text-xs text-gray-500 flex justify-between">
                                            <span>
                                                Priority: {transmission.priority.charAt(0).toUpperCase() + transmission.priority.slice(1)}
                                            </span>
                                            <span>
                                                {new Date(transmission.created_at).toLocaleString()}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    <div className="mt-4">
                        <Pagination 
                            currentPage={transmissions.current_page}
                            totalPages={transmissions.last_page}
                            route={route('transmission.index')}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}